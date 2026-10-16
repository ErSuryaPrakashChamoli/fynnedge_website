<?php

use App\Filament\Pages\ExperianDashboard;
use App\Filament\Pages\ExperianSettings;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\ExperianCreditRequests\ExperianCreditRequestResource;
use App\Filament\Resources\ExperianCreditRequests\Pages\ListExperianCreditRequests;
use App\Filament\Resources\ExperianCreditRequests\Pages\ViewExperianCreditRequest;
use App\Filament\Resources\JourneySessions\Pages\ViewJourneySession;
use App\Models\User;
use App\Modules\CreditBureau\Models\CreditConsent;
use App\Modules\CreditBureau\Services\CrifRoles;
use App\Modules\CreditBureau\Services\CrifSettings;
use App\Modules\Customers\Models\Customer;
use App\Modules\Equifax\Services\EquifaxSettings as EquifaxSettingsStore;
use App\Modules\Experian\Actions\FetchExperianPdf;
use App\Modules\Experian\Enums\ExperianErrorCode;
use App\Modules\Experian\Enums\ExperianProduct;
use App\Modules\Experian\Enums\ExperianRequestStatus;
use App\Modules\Experian\Enums\ExperianRequestType;
use App\Modules\Experian\Models\ExperianCreditRequest;
use App\Modules\Experian\Services\ExperianRoles;
use App\Modules\Experian\Services\ExperianSettings as ExperianSettingsStore;
use App\Modules\Journey\Models\JourneySession;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    CrifRoles::grant();
    ExperianRoles::grant();
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($this->admin);
});

function experianFixtureRequest(array $attributes = [], ?Closure $state = null): ExperianCreditRequest
{
    $customer = Customer::factory()->create(['full_name' => 'Test Fixture']);
    $factory = ExperianCreditRequest::factory();

    return ($state ? $state($factory) : $factory)->forPan('AAAPZ9999Z')->successful(experianFixture(), 792)->create([
        'customer_id' => $customer->id,
        'credit_consent_id' => CreditConsent::factory()->create(['journey_session_id' => null, 'customer_id' => $customer->id, 'pan_number' => 'AAAPZ9999Z'])->id,
        'mobile' => '9000000001',
        'mobile_masked' => '******0001',
        'provider_report_id' => '1760600000000',
        ...$attributes,
    ]);
}

it('renders every Experian admin screen, separate from CRIF and Equifax', function (string $path) {
    $this->get($path)->assertOk();
})->with([
    '/admin/experian-dashboard',
    '/admin/experian-requests',
    '/admin/experian-requests?tab=reports',
    '/admin/experian-requests?tab=scores',
    '/admin/experian-requests?tab=pdfs',
    '/admin/experian-requests?tab=failed',
    '/admin/experian-settings',
    '/admin/experian-audit-history',
]);

it('lists the Experian menu entries in their own group', function () {
    $this->get('/admin/experian-dashboard')
        ->assertSeeInOrder(['Experian Dashboard', 'Experian Credit Reports', 'Experian Score Requests', 'Experian PDF Reports', 'Experian Requests', 'Experian Failed Requests', 'Experian Settings', 'Experian Audit History']);
});

it('renders the full report with masked identifiers, escaped content and no secrets', function () {
    $body = experianFixture();
    $body['data']['credit_report']['CAIS_Account']['CAIS_Account_DETAILS'][1]['Subscriber_Name'] = '<script>alert("ex")</script>';
    $request = experianFixtureRequest(['raw_response' => $body]);

    $this->get(ExperianCreditRequestResource::getUrl('view', ['record' => $request]))
        ->assertOk()
        ->assertSeeInOrder(['Request overview', 'Experian score', 'Report header', 'Personal information', 'Identity information', 'Application Experian scored', 'Addresses', 'Phone numbers', 'Accounts summary', 'Accounts (2)', 'Credit enquiries', 'Non-credit enquiries', 'Other report sections', 'Processing history'])
        ->assertSee(['792', 'credit_report_experian_TESTFIXTURE0001', '1760600000000', 'XXXXXX999Z', '1 Fixture Lane', 'test.fixture@example.test', 'Fixture Bank', 'Credit card (10)', '₹45,210', '09-26 30', 'kept as sent', 'Confidence level'])
        ->assertSee('<script>alert("ex")</script>')
        ->assertDontSee('<script>alert("ex")</script>', escape: false)
        ->assertDontSee('AAAPZ9999Z')
        ->assertDontSee('9000000001')
        ->assertDontSee('test-surepass-token');
});

it('shows only the score and status to a role without ViewReport', function () {
    $request = experianFixtureRequest();
    $scoresOnly = User::factory()->create(['is_admin' => true]);
    $scoresOnly->syncRoles([]);
    $scoresOnly->givePermissionTo(['ViewAny:ExperianCreditRequest', 'View:ExperianCreditRequest']);

    $this->actingAs($scoresOnly)->get(ExperianCreditRequestResource::getUrl('view', ['record' => $request]))
        ->assertOk()
        ->assertSee('792')
        ->assertSee('Your role can see the score and status')
        ->assertDontSee('Fixture Bank')
        ->assertDontSee('1 Fixture Lane');
});

it('gives a Credit Officer the everyday Experian screens but nothing privileged, and staff without it nothing', function () {
    $request = experianFixtureRequest();
    $officer = tap(User::factory()->create(['is_admin' => true]))->syncRoles([CrifRoles::OFFICER_ROLE]);
    $this->actingAs($officer);

    $this->get('/admin/experian-requests')->assertOk();
    $this->get('/admin/experian-dashboard')->assertOk();
    $this->get('/admin/experian-settings')->assertForbidden();
    $this->get('/admin/experian-audit-history')->assertForbidden();
    Livewire::test(ViewExperianCreditRequest::class, ['record' => $request->getRouteKey()])
        ->assertActionVisible('requestExperianPdf')
        ->assertActionHidden('downloadExperianPdf')
        ->assertActionHidden('viewRawExperianJson')
        ->assertActionHidden('fetchFreshExperian');

    $this->actingAs(tap(User::factory()->create(['is_admin' => true]))->syncRoles(['Marketing']));
    $this->get('/admin/experian-requests')->assertForbidden();
    $this->get(ExperianCreditRequestResource::getUrl('view', ['record' => $request]))->assertForbidden();
});

it('fetches an Experian report from the customer page and records who did it', function () {
    fakeSurepassExperian();
    $customer = Customer::factory()->create(['full_name' => 'Test Fixture', 'phone' => '9000000001']);

    Livewire::test(EditCustomer::class, ['record' => $customer->getRouteKey()])
        ->mountAction(TestAction::make('fetchExperianReport'))
        ->assertSchemaStateSet(['full_name' => 'Test Fixture', 'mobile' => '9000000001', 'consent_source' => 'record'])
        ->fillForm([
            'pan' => 'AAAPZ9999Z',
            'consent_method' => 'written_form',
            'consented_at' => now()->subHour()->format('Y-m-d H:i:s'),
            'evidence_reference' => 'FORM-EX-1',
            'attested' => true,
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertNotified('Experian request sent');

    expect($customer->experianRequests()->sole())
        ->status->toBe(ExperianRequestStatus::Success)
        ->product->toBe(ExperianProduct::Report)
        ->requested_by->toBe($this->admin->id);
    Http::assertSent(fn (Request $request) => $request->data() === ['name' => 'Test Fixture', 'pan' => 'AAAPZ9999Z', 'mobile' => '9000000001', 'consent' => 'Y']);
});

it('validates the Experian request form before anything is sent', function () {
    fakeSurepassExperian();
    $customer = Customer::factory()->create();

    Livewire::test(EditCustomer::class, ['record' => $customer->getRouteKey()])
        ->callAction(TestAction::make('checkExperianScore'), data: ['full_name' => 'X', 'mobile' => '1', 'pan' => 'BAD', 'consent_source' => 'record'])
        ->assertHasFormErrors(['mobile', 'pan', 'consent_method', 'evidence_reference', 'attested']);

    expect(ExperianCreditRequest::query()->count())->toBe(0);
    Http::assertNothingSent();
});

it('prefills a request from an application and offers its website consent', function () {
    fakeSurepassExperian();
    $customer = Customer::factory()->create(['full_name' => 'Asha Kumari Rao']);
    $session = JourneySession::factory()->create(['customer_id' => $customer->id, 'phone_number' => '9000000002']);
    $consent = CreditConsent::factory()->create(['journey_session_id' => $session->id, 'pan_number' => 'AAAPZ9999Z']);

    Livewire::test(ViewJourneySession::class, ['record' => $session->getRouteKey()])
        ->mountAction(TestAction::make('fetchExperianReport'))
        ->assertSchemaStateSet(['full_name' => 'Asha Kumari Rao', 'mobile' => '9000000002', 'pan' => 'AAAPZ9999Z', 'consent_source' => $consent->id])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect($session->experianRequests()->sole())->customer_id->toBe($customer->id)->credit_consent_id->toBe($consent->id);
});

it('retries a failed request and reconciles an uncertain one from the request page', function () {
    fakeSurepassExperian();
    $failed = experianFixtureRequest()->forceFill(['status' => ExperianRequestStatus::Failed, 'error_code' => ExperianErrorCode::ApiNotEnabled, 'retryable' => true, 'raw_response' => null, 'score' => null]);
    $failed->save();

    Livewire::test(ViewExperianCreditRequest::class, ['record' => $failed->getRouteKey()])
        ->callAction('retryExperian')
        ->assertNotified('Experian request sent');

    expect(ExperianCreditRequest::query()->where('previous_request_id', $failed->id)->sole())
        ->request_type->toBe(ExperianRequestType::Retry)->status->toBe(ExperianRequestStatus::Success);

    $uncertain = ExperianCreditRequest::factory()->forPan('BBBPZ1111Z')->awaitingReconciliation()->create();
    Livewire::test(ViewExperianCreditRequest::class, ['record' => $uncertain->getRouteKey()])
        ->callAction('reconcileExperian', data: ['resolution' => 'not_processed', 'note' => 'Surepass usage log shows no charge'])
        ->assertHasNoFormErrors();

    expect($uncertain->refresh())->status->toBe(ExperianRequestStatus::Failed)->retryable->toBeTrue();
});

it('gets the PDF from a report as one paid PDF request, then downloads the stored copy', function () {
    Storage::fake(FetchExperianPdf::DISK);
    fakeSurepassExperian();
    $report = experianFixtureRequest();

    Livewire::test(ViewExperianCreditRequest::class, ['record' => $report->getRouteKey()])
        ->callAction('requestExperianPdf')
        ->assertHasNoFormErrors()
        ->assertNotified('Experian request sent');

    $pdf = ExperianCreditRequest::query()->where('product', ExperianProduct::Pdf)->sole();
    expect($pdf)->status->toBe(ExperianRequestStatus::Success)->previous_request_id->toBeNull()->credit_consent_id->toBe($report->credit_consent_id)
        ->and($pdf->hasStoredPdf())->toBeTrue();

    Livewire::test(ViewExperianCreditRequest::class, ['record' => $pdf->getRouteKey()])
        ->assertActionHidden('requestExperianPdf')
        ->callAction('downloadExperianPdf')
        ->assertFileDownloaded();
    Livewire::test(ViewExperianCreditRequest::class, ['record' => $pdf->getRouteKey()])
        ->callAction('downloadExperianPdf')
        ->assertFileDownloaded();

    Http::assertSentCount(2);
    expect(Storage::disk(FetchExperianPdf::DISK)->get($pdf->pdf_path))->not->toContain('%PDF')
        ->and($pdf->auditLogs()->pluck('action')->all())->toContain('pdf_fetched', 'pdf_downloaded');
});

it('names an Experian PDF API missing from the plan as the reason, without a charge', function () {
    fakeSurepassExperian(fn (Request $request) => str_ends_with($request->url(), '/fetch-report-pdf')
        ? Http::response(['success' => false, 'message' => 'Your access token is not valid for this API/Scope.'], 401)
        : Http::response(experianFixture()));
    $report = experianFixtureRequest();

    Livewire::test(ViewExperianCreditRequest::class, ['record' => $report->getRouteKey()])
        ->callAction('requestExperianPdf')
        ->assertNotified('Experian request sent');

    expect(ExperianCreditRequest::query()->where('product', ExperianProduct::Pdf)->sole())
        ->status->toBe(ExperianRequestStatus::Failed)
        ->error_code->toBe(ExperianErrorCode::ApiNotEnabled)
        ->retryable->toBeTrue();
});

it('shows the unmasked raw JSON only with ViewRaw and records each opening', function () {
    $request = experianFixtureRequest();

    Livewire::test(ViewExperianCreditRequest::class, ['record' => $request->getRouteKey()])
        ->mountAction('viewRawExperianJson')
        ->assertMountedActionModalSee('AAAPZ9999Z');

    expect($request->auditLogs()->pluck('action')->all())->toContain('viewed', 'raw_viewed');
});

it('filters requests by PAN through its hash and by tab', function () {
    $match = experianFixtureRequest();
    $score = ExperianCreditRequest::factory()->score()->forPan('BBBPZ1111Z')->successful()->create();
    $pdf = ExperianCreditRequest::factory()->pdf()->forPan('DDDPZ3333Z')->successful()->create();
    $failed = ExperianCreditRequest::factory()->forPan('CCCPZ2222Z')->failed()->create();

    Livewire::test(ListExperianCreditRequests::class)
        ->filterTable('pan', ['pan' => ' aaapz 9999z'])
        ->assertCanSeeTableRecords([$match])
        ->assertCanNotSeeTableRecords([$score, $pdf, $failed]);

    Livewire::test(ListExperianCreditRequests::class, ['activeTab' => 'failed'])
        ->assertCanSeeTableRecords([$failed])
        ->assertCanNotSeeTableRecords([$match, $score, $pdf]);

    Livewire::test(ListExperianCreditRequests::class, ['activeTab' => 'scores'])
        ->assertCanSeeTableRecords([$score])
        ->assertCanNotSeeTableRecords([$match, $pdf, $failed]);

    Livewire::test(ListExperianCreditRequests::class, ['activeTab' => 'pdfs'])
        ->assertCanSeeTableRecords([$pdf])
        ->assertCanNotSeeTableRecords([$match, $score, $failed]);
});

it('counts score, report and PDF requests, failures, reuse and blocked duplicates on the dashboard', function () {
    ExperianCreditRequest::factory()->successful()->create(['times_reused' => 2]);
    ExperianCreditRequest::factory()->score()->failed(ExperianErrorCode::ApiNotEnabled)->create();
    ExperianCreditRequest::factory()->pdf()->successful()->create();
    ExperianCreditRequest::factory()->awaitingReconciliation()->create(['duplicates_blocked' => 3]);

    $stats = collect(Livewire::test(ExperianDashboard::class)->instance()->stats())->map(fn (array $stat) => $stat['value'])->all();

    expect($stats)->toMatchArray([
        'Score requests' => 1,
        'Full-report requests' => 2,
        'PDF requests' => 1,
        'Successful' => 2,
        'Failed' => 1,
        'Needs reconciliation' => 1,
        'Duplicates prevented' => 3,
        'Results reused' => 2,
    ]);
    $this->get('/admin/experian-dashboard')->assertSee('API not enabled on the Surepass account')->assertSee('Latest requests');
});

it('saves the Experian settings independently of CRIF and Equifax, never shows the token, and checks access for free', function () {
    fakeSurepassExperian(fn (Request $request) => str_ends_with($request->url(), '/fetch-report')
        ? Http::response(['message' => 'Input payload validation failed'], 400)
        : Http::response(['success' => false, 'message' => 'Your access token is not valid for this API/Scope.'], 401));

    $this->get('/admin/experian-settings')->assertSee('Surepass API token is configured on this server')->assertSee('SUREPASS_BEARER_TOKEN')->assertDontSee('test-surepass-token');

    Livewire::test(ExperianSettings::class)
        ->fillForm(['experian_enabled' => false, 'experian_public_score_enabled' => true, 'experian_score_reuse_days' => 7, 'experian_report_reuse_days' => 45, 'experian_consent_validity_days' => 15, 'experian_retention_days' => 400, 'experian_alert_email' => 'ops@example.test'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->callAction('checkAccess')
        ->assertNotified('Surepass Experian access');

    expect(ExperianSettingsStore::enabled())->toBeFalse()
        ->and(ExperianSettingsStore::publicScoreEnabled())->toBeTrue()
        ->and(ExperianSettingsStore::scoreReuseDays())->toBe(7)
        ->and(ExperianSettingsStore::reportReuseDays())->toBe(45)
        ->and(ExperianSettingsStore::consentValidityDays())->toBe(15)
        ->and(CrifSettings::enabled())->toBeFalse()
        ->and(EquifaxSettingsStore::publicScoreEnabled())->toBeFalse();
    Http::assertSentCount(3);
    Http::assertNotSent(fn (Request $request) => $request->data() !== []);
});

it('lists Experian events in the Experian audit history', function () {
    $request = experianFixtureRequest();
    $request->recordEvent('viewed');

    $this->get('/admin/experian-audit-history')->assertOk()->assertSee('XXXXXX999Z');
});

it('shows the Experian tab and menu on the customer and application pages', function () {
    $request = experianFixtureRequest();

    $this->get('/admin/customers')->assertOk()->assertSee('Experian');
    Livewire::test(EditCustomer::class, ['record' => $request->customer->getRouteKey()])
        ->assertActionVisible(TestAction::make('fetchExperianReport'))
        ->assertActionVisible(TestAction::make('checkExperianScore'))
        ->assertActionVisible(TestAction::make('fetchExperianPdf'))
        ->assertActionVisible(TestAction::make('viewLatestExperian'));
});
