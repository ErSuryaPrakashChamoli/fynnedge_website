<?php

use App\Filament\Pages\CibilDashboard;
use App\Filament\Pages\CibilSettings;
use App\Filament\Resources\CibilCreditRequests\CibilCreditRequestResource;
use App\Filament\Resources\CibilCreditRequests\Pages\ListCibilCreditRequests;
use App\Filament\Resources\CibilCreditRequests\Pages\ViewCibilCreditRequest;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\JourneySessions\Pages\ViewJourneySession;
use App\Models\User;
use App\Modules\Cibil\Actions\FetchCibilPdf;
use App\Modules\Cibil\Enums\CibilErrorCode;
use App\Modules\Cibil\Enums\CibilProduct;
use App\Modules\Cibil\Enums\CibilRequestStatus;
use App\Modules\Cibil\Enums\CibilRequestType;
use App\Modules\Cibil\Models\CibilCreditRequest;
use App\Modules\Cibil\Services\CibilRoles;
use App\Modules\Cibil\Services\CibilSettings as CibilSettingsStore;
use App\Modules\CreditBureau\Models\CreditConsent;
use App\Modules\CreditBureau\Services\CrifRoles;
use App\Modules\CreditBureau\Services\CrifSettings;
use App\Modules\Customers\Models\Customer;
use App\Modules\Equifax\Services\EquifaxSettings as EquifaxSettingsStore;
use App\Modules\Journey\Models\JourneySession;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    CrifRoles::grant();
    CibilRoles::grant();
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($this->admin);
});

function cibilFixtureRequest(array $attributes = [], ?Closure $state = null): CibilCreditRequest
{
    $customer = Customer::factory()->create(['full_name' => 'Test Fixture']);
    $factory = CibilCreditRequest::factory();

    return ($state ? $state($factory) : $factory)->forPan('AAAPZ9999Z')->successful(cibilFixture(), 750)->create([
        'customer_id' => $customer->id,
        'credit_consent_id' => CreditConsent::factory()->create(['journey_session_id' => null, 'customer_id' => $customer->id, 'pan_number' => 'AAAPZ9999Z'])->id,
        'mobile' => '9000000001',
        'mobile_masked' => '******0001',
        'provider_report_id' => '9100000001',
        ...$attributes,
    ]);
}

it('renders every CIBIL admin screen, separate from CRIF and Equifax', function (string $path) {
    $this->get($path)->assertOk();
})->with([
    '/admin/cibil-dashboard',
    '/admin/cibil-requests',
    '/admin/cibil-requests?tab=reports',
    '/admin/cibil-requests?tab=scores',
    '/admin/cibil-requests?tab=pdfs',
    '/admin/cibil-requests?tab=failed',
    '/admin/cibil-settings',
    '/admin/cibil-audit-history',
]);

it('lists the CIBIL menu entries in their own group', function () {
    $this->get('/admin/cibil-dashboard')
        ->assertSeeInOrder(['CIBIL Dashboard', 'CIBIL Credit Reports', 'CIBIL Score Requests', 'CIBIL PDF Reports', 'CIBIL Requests', 'CIBIL Failed Requests', 'CIBIL Settings', 'CIBIL Audit History']);
});

it('renders the full report with masked identifiers, escaped content and no secrets', function () {
    $body = cibilFixture();
    $body['data']['credit_report'][0]['accounts'][1]['memberShortName'] = '<script>alert("ex")</script>';
    $request = cibilFixtureRequest(['raw_response' => $body]);

    $this->get(CibilCreditRequestResource::getUrl('view', ['record' => $request]))
        ->assertOk()
        ->assertSeeInOrder(['Request overview', 'CIBIL score', 'Report records', 'Personal information', 'Identity information', 'Addresses', 'Phone numbers', 'Email addresses', 'Employment', 'Account summary', 'Enquiry summary', 'Accounts (2)', 'Enquiries', 'Other report sections', 'Processing history'])
        ->assertSee(['750', 'credit_report_cibil_TESTFIXTURE0001', '9100000001', 'XXXXXX999Z', '1 FIXTURE LANE', 'test.fixture@example.test', 'FIXTURE BANK', 'Credit card (10)', '₹45,210', '09-26 030', 'kept as sent', 'Reason codes', 'Male'])
        ->assertSee('<script>alert("ex")</script>')
        ->assertDontSee('<script>alert("ex")</script>', escape: false)
        ->assertDontSee('AAAPZ9999Z')
        ->assertDontSee('9000000001')
        ->assertDontSee('test-surepass-token');
});

it('shows only the score and status to a role without ViewReport', function () {
    $request = cibilFixtureRequest();
    $scoresOnly = User::factory()->create(['is_admin' => true]);
    $scoresOnly->syncRoles([]);
    $scoresOnly->givePermissionTo(['ViewAny:CibilCreditRequest', 'View:CibilCreditRequest']);

    $this->actingAs($scoresOnly)->get(CibilCreditRequestResource::getUrl('view', ['record' => $request]))
        ->assertOk()
        ->assertSee('750')
        ->assertSee('Your role can see the score and status')
        ->assertDontSee('FIXTURE BANK')
        ->assertDontSee('1 FIXTURE LANE');
});

it('gives a Credit Officer the everyday CIBIL screens but nothing privileged, and staff without it nothing', function () {
    $request = cibilFixtureRequest();
    $officer = tap(User::factory()->create(['is_admin' => true]))->syncRoles([CrifRoles::OFFICER_ROLE]);
    $this->actingAs($officer);

    $this->get('/admin/cibil-requests')->assertOk();
    $this->get('/admin/cibil-dashboard')->assertOk();
    $this->get('/admin/cibil-settings')->assertForbidden();
    $this->get('/admin/cibil-audit-history')->assertForbidden();
    Livewire::test(ViewCibilCreditRequest::class, ['record' => $request->getRouteKey()])
        ->assertActionVisible('requestCibilPdf')
        ->assertActionHidden('downloadCibilPdf')
        ->assertActionHidden('viewRawCibilJson')
        ->assertActionHidden('fetchFreshCIBIL');

    $this->actingAs(tap(User::factory()->create(['is_admin' => true]))->syncRoles(['Marketing']));
    $this->get('/admin/cibil-requests')->assertForbidden();
    $this->get(CibilCreditRequestResource::getUrl('view', ['record' => $request]))->assertForbidden();
});

it('fetches a CIBIL report from the customer page and records who did it', function () {
    fakeSurepassCibil();
    $customer = Customer::factory()->create(['full_name' => 'Test Fixture', 'phone' => '9000000001']);

    Livewire::test(EditCustomer::class, ['record' => $customer->getRouteKey()])
        ->mountAction(TestAction::make('fetchCibilReport'))
        ->assertSchemaStateSet(['full_name' => 'Test Fixture', 'mobile' => '9000000001', 'consent_source' => 'record'])
        ->fillForm([
            'pan' => 'AAAPZ9999Z',
            'gender' => 'female',
            'consent_method' => 'written_form',
            'consented_at' => now()->subHour()->format('Y-m-d H:i:s'),
            'evidence_reference' => 'FORM-EX-1',
            'attested' => true,
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertNotified('CIBIL request sent');

    expect($customer->cibilRequests()->sole())
        ->status->toBe(CibilRequestStatus::Success)
        ->product->toBe(CibilProduct::Report)
        ->requested_by->toBe($this->admin->id);
    Http::assertSent(fn (Request $request) => $request->data() === ['mobile' => '9000000001', 'pan' => 'AAAPZ9999Z', 'name' => 'Test Fixture', 'gender' => 'female', 'consent' => 'Y']);
});

it('validates the CIBIL request form before anything is sent', function () {
    fakeSurepassCibil();
    $customer = Customer::factory()->create();

    Livewire::test(EditCustomer::class, ['record' => $customer->getRouteKey()])
        ->callAction(TestAction::make('checkCibilScore'), data: ['full_name' => 'X', 'mobile' => '1', 'pan' => 'BAD', 'consent_source' => 'record'])
        ->assertHasFormErrors(['mobile', 'pan', 'gender', 'consent_method', 'evidence_reference', 'attested']);

    expect(CibilCreditRequest::query()->count())->toBe(0);
    Http::assertNothingSent();
});

it('prefills a request from an application and offers its website consent', function () {
    fakeSurepassCibil();
    $customer = Customer::factory()->create(['full_name' => 'Asha Kumari Rao']);
    $session = JourneySession::factory()->create(['customer_id' => $customer->id, 'phone_number' => '9000000002']);
    $consent = CreditConsent::factory()->create(['journey_session_id' => $session->id, 'pan_number' => 'AAAPZ9999Z']);

    Livewire::test(ViewJourneySession::class, ['record' => $session->getRouteKey()])
        ->mountAction(TestAction::make('fetchCibilReport'))
        ->assertSchemaStateSet(['full_name' => 'Asha Kumari Rao', 'mobile' => '9000000002', 'pan' => 'AAAPZ9999Z', 'consent_source' => $consent->id])
        ->fillForm(['gender' => 'male'])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect($session->cibilRequests()->sole())->customer_id->toBe($customer->id)->credit_consent_id->toBe($consent->id);
});

it('retries a failed request and reconciles an uncertain one from the request page', function () {
    fakeSurepassCibil();
    $failed = cibilFixtureRequest()->forceFill(['status' => CibilRequestStatus::Failed, 'error_code' => CibilErrorCode::ApiNotEnabled, 'retryable' => true, 'raw_response' => null, 'score' => null]);
    $failed->save();

    Livewire::test(ViewCibilCreditRequest::class, ['record' => $failed->getRouteKey()])
        ->callAction('retryCIBIL')
        ->assertNotified('CIBIL request sent');

    expect(CibilCreditRequest::query()->where('previous_request_id', $failed->id)->sole())
        ->request_type->toBe(CibilRequestType::Retry)->status->toBe(CibilRequestStatus::Success);

    $uncertain = CibilCreditRequest::factory()->forPan('BBBPZ1111Z')->awaitingReconciliation()->create();
    Livewire::test(ViewCibilCreditRequest::class, ['record' => $uncertain->getRouteKey()])
        ->callAction('reconcileCIBIL', data: ['resolution' => 'not_processed', 'note' => 'Surepass usage log shows no charge'])
        ->assertHasNoFormErrors();

    expect($uncertain->refresh())->status->toBe(CibilRequestStatus::Failed)->retryable->toBeTrue();
});

it('gets the PDF from a report as one paid PDF request, then downloads the stored copy', function () {
    Storage::fake(FetchCibilPdf::DISK);
    fakeSurepassCibil();
    $report = cibilFixtureRequest();

    Livewire::test(ViewCibilCreditRequest::class, ['record' => $report->getRouteKey()])
        ->callAction('requestCibilPdf')
        ->assertHasNoFormErrors()
        ->assertNotified('CIBIL request sent');

    $pdf = CibilCreditRequest::query()->where('product', CibilProduct::Pdf)->sole();
    expect($pdf)->status->toBe(CibilRequestStatus::Success)->previous_request_id->toBeNull()->credit_consent_id->toBe($report->credit_consent_id)
        ->and($pdf->hasStoredPdf())->toBeTrue();

    Livewire::test(ViewCibilCreditRequest::class, ['record' => $pdf->getRouteKey()])
        ->assertActionHidden('requestCibilPdf')
        ->callAction('downloadCibilPdf')
        ->assertFileDownloaded();
    Livewire::test(ViewCibilCreditRequest::class, ['record' => $pdf->getRouteKey()])
        ->callAction('downloadCibilPdf')
        ->assertFileDownloaded();

    Http::assertSentCount(2);
    expect(Storage::disk(FetchCibilPdf::DISK)->get($pdf->pdf_path))->not->toContain('%PDF')
        ->and($pdf->auditLogs()->pluck('action')->all())->toContain('pdf_fetched', 'pdf_downloaded');
});

it('names a CIBIL PDF API missing from the plan as the reason, without a charge', function () {
    fakeSurepassCibil(fn (Request $request) => str_ends_with($request->url(), '/fetch-report-pdf')
        ? Http::response(['success' => false, 'message' => 'Your access token is not valid for this API/Scope.'], 401)
        : Http::response(cibilFixture()));
    $report = cibilFixtureRequest();

    Livewire::test(ViewCibilCreditRequest::class, ['record' => $report->getRouteKey()])
        ->callAction('requestCibilPdf')
        ->assertNotified('CIBIL request sent');

    expect(CibilCreditRequest::query()->where('product', CibilProduct::Pdf)->sole())
        ->status->toBe(CibilRequestStatus::Failed)
        ->error_code->toBe(CibilErrorCode::ApiNotEnabled)
        ->retryable->toBeTrue();
});

it('shows the unmasked raw JSON only with ViewRaw and records each opening', function () {
    $request = cibilFixtureRequest();

    Livewire::test(ViewCibilCreditRequest::class, ['record' => $request->getRouteKey()])
        ->mountAction('viewRawCibilJson')
        ->assertMountedActionModalSee('AAAPZ9999Z');

    expect($request->auditLogs()->pluck('action')->all())->toContain('viewed', 'raw_viewed');
});

it('filters requests by PAN through its hash and by tab', function () {
    $match = cibilFixtureRequest();
    $score = CibilCreditRequest::factory()->score()->forPan('BBBPZ1111Z')->successful()->create();
    $pdf = CibilCreditRequest::factory()->pdf()->forPan('DDDPZ3333Z')->successful()->create();
    $failed = CibilCreditRequest::factory()->forPan('CCCPZ2222Z')->failed()->create();

    Livewire::test(ListCibilCreditRequests::class)
        ->filterTable('pan', ['pan' => ' aaapz 9999z'])
        ->assertCanSeeTableRecords([$match])
        ->assertCanNotSeeTableRecords([$score, $pdf, $failed]);

    Livewire::test(ListCibilCreditRequests::class, ['activeTab' => 'failed'])
        ->assertCanSeeTableRecords([$failed])
        ->assertCanNotSeeTableRecords([$match, $score, $pdf]);

    Livewire::test(ListCibilCreditRequests::class, ['activeTab' => 'scores'])
        ->assertCanSeeTableRecords([$score])
        ->assertCanNotSeeTableRecords([$match, $pdf, $failed]);

    Livewire::test(ListCibilCreditRequests::class, ['activeTab' => 'pdfs'])
        ->assertCanSeeTableRecords([$pdf])
        ->assertCanNotSeeTableRecords([$match, $score, $failed]);
});

it('counts score, report and PDF requests, failures, reuse and blocked duplicates on the dashboard', function () {
    CibilCreditRequest::factory()->successful()->create(['times_reused' => 2]);
    CibilCreditRequest::factory()->score()->failed(CibilErrorCode::ApiNotEnabled)->create();
    CibilCreditRequest::factory()->pdf()->successful()->create();
    CibilCreditRequest::factory()->awaitingReconciliation()->create(['duplicates_blocked' => 3]);

    $stats = collect(Livewire::test(CibilDashboard::class)->instance()->stats())->map(fn (array $stat) => $stat['value'])->all();

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
    $this->get('/admin/cibil-dashboard')->assertSee('API not enabled on the Surepass account')->assertSee('Latest requests');
});

it('saves the CIBIL settings independently of the other bureaus, never shows the token, and checks access for free', function () {
    fakeSurepassCibil(fn (Request $request) => str_ends_with($request->url(), '/fetch-report')
        ? Http::response(['message' => 'Input payload validation failed'], 400)
        : Http::response(['success' => false, 'message' => 'Your access token is not valid for this API/Scope.'], 401));

    $this->get('/admin/cibil-settings')
        ->assertSee('CIBIL API token is configured on this server')
        ->assertSee('SUREPASS_CIBIL_TOKEN')
        ->assertSee('Answered by the CIBIL bureau through Surepass')
        ->assertDontSee('test-cibil-token');

    Livewire::test(CibilSettings::class)
        ->fillForm(['cibil_enabled' => false, 'cibil_public_score_enabled' => true, 'cibil_score_reuse_days' => 7, 'cibil_report_reuse_days' => 45, 'cibil_consent_validity_days' => 15, 'cibil_retention_days' => 400, 'cibil_alert_email' => 'ops@example.test'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->callAction('checkAccess')
        ->assertNotified('Surepass CIBIL access');

    expect(CibilSettingsStore::enabled())->toBeFalse()
        ->and(CibilSettingsStore::publicScoreEnabled())->toBeTrue()
        ->and(CibilSettingsStore::scoreReuseDays())->toBe(7)
        ->and(CibilSettingsStore::reportReuseDays())->toBe(45)
        ->and(CibilSettingsStore::consentValidityDays())->toBe(15)
        ->and(CrifSettings::enabled())->toBeFalse()
        ->and(EquifaxSettingsStore::publicScoreEnabled())->toBeFalse();
    Http::assertSentCount(3);
    Http::assertNotSent(fn (Request $request) => $request->data() !== []);
});

it('lists CIBIL events in the CIBIL audit history', function () {
    $request = cibilFixtureRequest();
    $request->recordEvent('viewed');

    $this->get('/admin/cibil-audit-history')->assertOk()->assertSee('XXXXXX999Z');
});

it('shows the CIBIL tab and menu on the customer and application pages', function () {
    $request = cibilFixtureRequest();

    $this->get('/admin/customers')->assertOk()->assertSee('CIBIL');
    Livewire::test(EditCustomer::class, ['record' => $request->customer->getRouteKey()])
        ->assertActionVisible(TestAction::make('fetchCibilReport'))
        ->assertActionVisible(TestAction::make('checkCibilScore'))
        ->assertActionVisible(TestAction::make('fetchCibilPdf'))
        ->assertActionVisible(TestAction::make('viewLatestCIBIL'));
});
