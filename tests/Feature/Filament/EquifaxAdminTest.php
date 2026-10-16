<?php

use App\Filament\Pages\EquifaxDashboard;
use App\Filament\Pages\EquifaxSettings;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\EquifaxCreditRequests\EquifaxCreditRequestResource;
use App\Filament\Resources\EquifaxCreditRequests\Pages\ListEquifaxCreditRequests;
use App\Filament\Resources\EquifaxCreditRequests\Pages\ViewEquifaxCreditRequest;
use App\Filament\Resources\JourneySessions\Pages\ViewJourneySession;
use App\Models\User;
use App\Modules\CreditBureau\Models\CreditConsent;
use App\Modules\CreditBureau\Services\CrifRoles;
use App\Modules\CreditBureau\Services\CrifSettings;
use App\Modules\Customers\Models\Customer;
use App\Modules\Equifax\Enums\EquifaxErrorCode;
use App\Modules\Equifax\Enums\EquifaxProduct;
use App\Modules\Equifax\Enums\EquifaxRequestStatus;
use App\Modules\Equifax\Enums\EquifaxRequestType;
use App\Modules\Equifax\Models\EquifaxCreditRequest;
use App\Modules\Equifax\Services\EquifaxRoles;
use App\Modules\Equifax\Services\EquifaxSettings as EquifaxSettingsStore;
use App\Modules\Journey\Models\JourneySession;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    CrifRoles::grant();
    EquifaxRoles::grant();
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($this->admin);
});

function equifaxFixtureRequest(array $attributes = []): EquifaxCreditRequest
{
    $customer = Customer::factory()->create(['full_name' => 'Test Fixture']);

    return EquifaxCreditRequest::factory()->forPan('AAAPZ9999Z')->successful(equifaxReportFixture())->create([
        'customer_id' => $customer->id,
        'credit_consent_id' => CreditConsent::factory()->create(['journey_session_id' => null, 'customer_id' => $customer->id])->id,
        'mobile' => '9000000001',
        'mobile_masked' => '******0001',
        'provider_report_id' => 'FIXTURE-ORDER-0001',
        ...$attributes,
    ]);
}

it('renders every Equifax admin screen, separate from CRIF', function (string $path) {
    $this->get($path)->assertOk();
})->with([
    '/admin/equifax-dashboard',
    '/admin/equifax-requests',
    '/admin/equifax-requests?tab=reports',
    '/admin/equifax-requests?tab=failed',
    '/admin/equifax-settings',
    '/admin/equifax-audit-history',
]);

it('lists the Equifax menu entries in their own group', function () {
    $this->get('/admin/equifax-dashboard')
        ->assertSeeInOrder(['Equifax Dashboard', 'Equifax Credit Reports', 'Equifax Requests', 'Equifax Failed Requests', 'Equifax Settings', 'Equifax Audit History']);
});

it('renders the full report with masked identifiers, escaped content and no secrets', function () {
    fakeSurepassEquifax();
    $body = equifaxReportFixture();
    $body['data']['credit_report']['credit_report'][0]['CCRResponse']['CIRReportDataLst'][0]['CIRReportData']['RetailAccountDetails'][1]['Institution'] = '<script>alert("eq")</script>';
    $request = equifaxFixtureRequest(['raw_response' => $body]);

    $this->get(EquifaxCreditRequestResource::getUrl('view', ['record' => $request]))
        ->assertOk()
        ->assertSeeInOrder(['Request overview', 'Equifax score', 'Personal information', 'Identity information', 'Addresses', 'Phone numbers', 'Accounts summary', 'Accounts (2)', 'Enquiry history', 'Other report sections', 'Processing history'])
        ->assertSee(['761', 'XXXXXX999Z', 'XXXX0001', '2 SAMPLE LANE', 'fixture@example.test', 'FIXTURE BANK', '₹2,68,889', '09-26 030', 'kept as sent', 'FIXTURE NBFC', 'Fixture scoring factor', 'FixtureFutureFlag'])
        ->assertSee('<script>alert("eq")</script>')
        ->assertDontSee('<script>alert("eq")</script>', escape: false)
        ->assertDontSee('AAAPZ9999Z')
        ->assertDontSee('9000000001')
        ->assertDontSee('test-surepass-token');
});

it('shows only the score and status to a role without ViewReport', function () {
    $request = equifaxFixtureRequest();
    $scoresOnly = User::factory()->create(['is_admin' => true]);
    $scoresOnly->syncRoles([]);
    $scoresOnly->givePermissionTo(['ViewAny:EquifaxCreditRequest', 'View:EquifaxCreditRequest']);

    $this->actingAs($scoresOnly)->get(EquifaxCreditRequestResource::getUrl('view', ['record' => $request]))
        ->assertOk()
        ->assertSee('761')
        ->assertSee('Your role can see the score and status')
        ->assertDontSee('FIXTURE BANK')
        ->assertDontSee('2 SAMPLE LANE');
});

it('gives a Credit Officer the everyday Equifax screens but nothing privileged, and staff without it nothing', function () {
    $request = equifaxFixtureRequest();
    $officer = tap(User::factory()->create(['is_admin' => true]))->syncRoles([CrifRoles::OFFICER_ROLE]);
    $this->actingAs($officer);

    $this->get('/admin/equifax-requests')->assertOk();
    $this->get('/admin/equifax-dashboard')->assertOk();
    $this->get('/admin/equifax-settings')->assertForbidden();
    $this->get('/admin/equifax-audit-history')->assertForbidden();
    Livewire::test(ViewEquifaxCreditRequest::class, ['record' => $request->getRouteKey()])
        ->assertActionVisible('downloadEquifaxPdf')
        ->assertActionHidden('viewRawEquifaxJson')
        ->assertActionHidden('fetchFreshEquifax');

    $this->actingAs(tap(User::factory()->create(['is_admin' => true]))->syncRoles(['Marketing']));
    $this->get('/admin/equifax-requests')->assertForbidden();
    $this->get(EquifaxCreditRequestResource::getUrl('view', ['record' => $request]))->assertForbidden();
});

it('fetches an Equifax report from the customer page and records who did it', function () {
    fakeSurepassEquifax();
    $customer = Customer::factory()->create(['full_name' => 'Test Fixture', 'phone' => '9000000001']);

    Livewire::test(EditCustomer::class, ['record' => $customer->getRouteKey()])
        ->mountAction(TestAction::make('fetchEquifaxReport'))
        ->assertSchemaStateSet(['full_name' => 'Test Fixture', 'mobile' => '9000000001', 'consent_source' => 'record'])
        ->fillForm([
            'pan' => 'AAAPZ9999Z',
            'gender' => 'female',
            'consent_method' => 'written_form',
            'consented_at' => now()->subHour()->format('Y-m-d H:i:s'),
            'evidence_reference' => 'FORM-EQ-1',
            'attested' => true,
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertNotified('Equifax request sent');

    expect($customer->equifaxRequests()->sole())
        ->status->toBe(EquifaxRequestStatus::Success)
        ->product->toBe(EquifaxProduct::Report)
        ->requested_by->toBe($this->admin->id);
    Http::assertSent(fn ($request) => $request['gender'] === 'female' && $request['name'] === 'Test Fixture');
});

it('validates the Equifax request form before anything is sent', function () {
    fakeSurepassEquifax();
    $customer = Customer::factory()->create();

    Livewire::test(EditCustomer::class, ['record' => $customer->getRouteKey()])
        ->callAction(TestAction::make('fetchEquifaxReport'), data: ['full_name' => 'X', 'mobile' => '1', 'pan' => 'BAD', 'gender' => null, 'consent_source' => 'record'])
        ->assertHasFormErrors(['mobile', 'pan', 'gender', 'consent_method', 'evidence_reference', 'attested']);

    expect(EquifaxCreditRequest::query()->count())->toBe(0);
    Http::assertNothingSent();
});

it('prefills a request from an application and offers its website consent', function () {
    fakeSurepassEquifax();
    $customer = Customer::factory()->create(['full_name' => 'Asha Kumari Rao']);
    $session = JourneySession::factory()->create(['customer_id' => $customer->id, 'phone_number' => '9000000002']);
    $consent = CreditConsent::factory()->create(['journey_session_id' => $session->id, 'pan_number' => 'AAAPZ9999Z']);

    Livewire::test(ViewJourneySession::class, ['record' => $session->getRouteKey()])
        ->mountAction(TestAction::make('fetchEquifaxReport'))
        ->assertSchemaStateSet(['full_name' => 'Asha Kumari Rao', 'mobile' => '9000000002', 'pan' => 'AAAPZ9999Z', 'consent_source' => $consent->id])
        ->fillForm(['gender' => 'female'])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect($session->equifaxRequests()->sole())->customer_id->toBe($customer->id)->credit_consent_id->toBe($consent->id);
});

it('retries a failed request and reconciles an uncertain one from the request page', function () {
    fakeSurepassEquifax();
    $failed = equifaxFixtureRequest()->forceFill(['status' => EquifaxRequestStatus::Failed, 'error_code' => EquifaxErrorCode::ApiNotEnabled, 'retryable' => true, 'raw_response' => null, 'score' => null]);
    $failed->save();

    Livewire::test(ViewEquifaxCreditRequest::class, ['record' => $failed->getRouteKey()])
        ->callAction('retryEquifax')
        ->assertNotified('Equifax request sent');

    expect(EquifaxCreditRequest::query()->where('previous_request_id', $failed->id)->sole())
        ->request_type->toBe(EquifaxRequestType::Retry)->status->toBe(EquifaxRequestStatus::Success);

    $uncertain = EquifaxCreditRequest::factory()->forPan('BBBPZ1111Z')->awaitingReconciliation()->create();
    Livewire::test(ViewEquifaxCreditRequest::class, ['record' => $uncertain->getRouteKey()])
        ->callAction('reconcileEquifax', data: ['resolution' => 'not_processed', 'note' => 'Surepass usage log shows no charge'])
        ->assertHasNoFormErrors();

    expect($uncertain->refresh())->status->toBe(EquifaxRequestStatus::Failed)->retryable->toBeTrue();
});

it('downloads the PDF from the report link once, then from the stored copy', function () {
    fakeSurepassEquifax();
    Http::fake(['files.example.test/*' => Http::response('%PDF-1.7 equifax fixture', 200)]);
    Storage::fake('local');
    $body = equifaxReportFixture();
    $body['data']['credit_report_link'] = 'https://files.example.test/eq.pdf';
    $request = equifaxFixtureRequest(['raw_response' => $body]);

    Livewire::test(ViewEquifaxCreditRequest::class, ['record' => $request->getRouteKey()])
        ->assertActionHasLabel('downloadEquifaxPdf', 'Download PDF')
        ->callAction('downloadEquifaxPdf')
        ->assertFileDownloaded();
    Livewire::test(ViewEquifaxCreditRequest::class, ['record' => $request->getRouteKey()])
        ->callAction('downloadEquifaxPdf')
        ->assertFileDownloaded();

    Http::assertSentCount(1);
    Http::assertNotSent(fn ($sent) => str_contains($sent->url(), 'credit-report-v2'));
    expect(Storage::disk('local')->get($request->refresh()->pdf_path))->not->toContain('%PDF');
});

it('names an Equifax PDF API missing from the plan as the reason', function () {
    fakeSurepassEquifax(Http::response(['success' => false, 'message' => 'Your access token is not valid for this API/Scope.'], 401));
    $request = equifaxFixtureRequest();

    Livewire::test(ViewEquifaxCreditRequest::class, ['record' => $request->getRouteKey()])
        ->assertActionHasLabel('downloadEquifaxPdf', 'Get PDF from Surepass')
        ->callAction('downloadEquifaxPdf')
        ->assertNotified('Not done');

    expect($request->refresh()->pdf_error_code)->toBe('api_not_enabled');
});

it('shows the unmasked raw JSON only with ViewRaw and records each opening', function () {
    $request = equifaxFixtureRequest();

    Livewire::test(ViewEquifaxCreditRequest::class, ['record' => $request->getRouteKey()])
        ->mountAction('viewRawEquifaxJson')
        ->assertMountedActionModalSee('AAAPZ9999Z');

    expect($request->auditLogs()->pluck('action')->all())->toContain('viewed', 'raw_viewed');
});

it('filters requests by PAN through its hash and by tab', function () {
    $match = equifaxFixtureRequest();
    $score = EquifaxCreditRequest::factory()->score()->forPan('BBBPZ1111Z')->successful()->create();
    $failed = EquifaxCreditRequest::factory()->forPan('CCCPZ2222Z')->failed()->create();

    Livewire::test(ListEquifaxCreditRequests::class)
        ->filterTable('pan', ['pan' => ' aaapz 9999z'])
        ->assertCanSeeTableRecords([$match])
        ->assertCanNotSeeTableRecords([$score, $failed]);

    Livewire::test(ListEquifaxCreditRequests::class, ['activeTab' => 'failed'])
        ->assertCanSeeTableRecords([$failed])
        ->assertCanNotSeeTableRecords([$match, $score]);

    Livewire::test(ListEquifaxCreditRequests::class, ['activeTab' => 'scores'])
        ->assertCanSeeTableRecords([$score])
        ->assertCanNotSeeTableRecords([$match, $failed]);
});

it('counts score and report requests, failures, reuse and blocked duplicates on the dashboard', function () {
    EquifaxCreditRequest::factory()->successful()->create(['times_reused' => 2]);
    EquifaxCreditRequest::factory()->score()->failed(EquifaxErrorCode::ApiNotEnabled)->create();
    EquifaxCreditRequest::factory()->awaitingReconciliation()->create(['duplicates_blocked' => 3]);

    $stats = collect(Livewire::test(EquifaxDashboard::class)->instance()->stats())->map(fn (array $stat) => $stat['value'])->all();

    expect($stats)->toMatchArray([
        'Score requests' => 1,
        'Full-report requests' => 2,
        'Successful' => 1,
        'Failed' => 1,
        'Needs reconciliation' => 1,
        'Duplicates prevented' => 3,
        'Results reused' => 2,
    ]);
    $this->get('/admin/equifax-dashboard')->assertSee('API not enabled on the Surepass account');
});

it('saves the Equifax settings independently of CRIF and never shows the token', function () {
    config(['services.surepass.token' => 'test-surepass-token']);

    $this->get('/admin/equifax-settings')->assertSee('Surepass API token is configured on this server')->assertDontSee('test-surepass-token');

    Livewire::test(EquifaxSettings::class)
        ->fillForm(['equifax_enabled' => true, 'equifax_public_score_enabled' => true, 'equifax_score_reuse_days' => 7, 'equifax_report_reuse_days' => 45, 'equifax_consent_validity_days' => 15, 'equifax_retention_days' => 400, 'equifax_alert_email' => 'ops@example.test'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(EquifaxSettingsStore::enabled())->toBeTrue()
        ->and(EquifaxSettingsStore::publicScoreEnabled())->toBeTrue()
        ->and(EquifaxSettingsStore::scoreReuseDays())->toBe(7)
        ->and(EquifaxSettingsStore::reportReuseDays())->toBe(45)
        ->and(EquifaxSettingsStore::consentValidityDays())->toBe(15)
        ->and(CrifSettings::enabled())->toBeFalse();
});

it('lists Equifax events in the Equifax audit history', function () {
    $request = equifaxFixtureRequest();
    $request->recordEvent('viewed');

    $this->get('/admin/equifax-audit-history')->assertOk()->assertSee('XXXXXX999Z');
});
