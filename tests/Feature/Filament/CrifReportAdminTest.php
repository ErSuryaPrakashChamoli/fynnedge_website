<?php

use App\Filament\Pages\CrifDashboard;
use App\Filament\Pages\CrifSettings;
use App\Filament\RelationManagers\CrifReportsRelationManager;
use App\Filament\Resources\CrifReports\CrifReportResource;
use App\Filament\Resources\CrifReports\Pages\ListCrifReports;
use App\Filament\Resources\CrifReports\Pages\ViewCrifReport;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\JourneySessions\Pages\ViewJourneySession;
use App\Models\Setting;
use App\Models\User;
use App\Modules\CreditBureau\Enums\CrifErrorCode;
use App\Modules\CreditBureau\Enums\CrifReportStatus;
use App\Modules\CreditBureau\Enums\CrifRequestType;
use App\Modules\CreditBureau\Models\CreditConsent;
use App\Modules\CreditBureau\Models\CrifReport;
use App\Modules\CreditBureau\Services\CrifRoles;
use App\Modules\CreditBureau\Services\CrifSettings as CrifSettingsStore;
use App\Modules\Customers\Models\Customer;
use App\Modules\Journey\Models\JourneySession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    CrifRoles::grant();
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($this->admin);
});

function adminWithRoles(string ...$roles): User
{
    return tap(User::factory()->create(['is_admin' => true]))->syncRoles($roles);
}

function fixtureCrifReport(array $attributes = []): CrifReport
{
    $customer = Customer::factory()->create(['full_name' => 'Test Fixture']);

    return CrifReport::factory()->forPan('AAAPZ9999Z')->successful(crifReportFixture())->create([
        'customer_id' => $customer->id,
        'credit_consent_id' => CreditConsent::factory()->create(['journey_session_id' => null, 'customer_id' => $customer->id])->id,
        'mobile' => '9000000001',
        'mobile_masked' => '******0001',
        'provider_report_id' => 'FIXTURE-REPORT-0001',
        ...$attributes,
    ]);
}

it('renders every CRIF admin screen', function (string $path) {
    $this->get($path)->assertOk();
})->with([
    '/admin/crif-dashboard',
    '/admin/crif-reports',
    '/admin/crif-settings',
]);

it('renders the full report with masked identifiers and no secrets', function () {
    fakeSurepassCrif();
    $report = fixtureCrifReport();

    $this->get(CrifReportResource::getUrl('view', ['record' => $report]))
        ->assertOk()
        ->assertSeeInOrder(['Report overview', 'Score', 'Credit summary', 'Accounts (3)', 'Personal information variations', 'Inquiry history', 'Other report sections', 'Processing history'])
        ->assertSee(['XXXXXX999Z', '******0001', '742', 'FIXTURE BANK', 'FIXTURE CARDS', '₹2,68,889', 'SF05', 'FIXTURE NBFC', 'FIXTURE-FUTURE-SECTION', 'kept as sent', 'FIXTURE-REPORT-0001'])
        ->assertDontSee('AAAPZ9999Z')
        ->assertDontSee('9000000001')
        ->assertDontSee('test-surepass-token');
});

it('escapes markup arriving inside the provider response', function () {
    $body = crifReportFixture();
    $body['data']['credit_report']['RESPONSES']['RESPONSE'][0]['LOAN-DETAILS']['CREDIT-GUARANTOR'] = '<script>alert("lender")</script>';
    $body['data']['credit_report']['FIXTURE-FUTURE-SECTION']['NEW-FLAG'] = '<img src=x onerror=alert(1)>';
    $report = fixtureCrifReport(['raw_response' => $body]);

    $this->get(CrifReportResource::getUrl('view', ['record' => $report]))
        ->assertOk()
        ->assertSee('<script>alert("lender")</script>')
        ->assertDontSee('<script>alert("lender")</script>', escape: false)
        ->assertDontSee('<img src=x onerror=alert(1)>', escape: false);
});

it('renders a report whose optional sections are missing', function () {
    $report = fixtureCrifReport(['raw_response' => ['success' => true, 'data' => ['credit_report' => ['HEADER' => ['REPORT-ID' => 'SPARSE-1'], 'RESPONSES' => '']]]]);

    $this->get(CrifReportResource::getUrl('view', ['record' => $report]))
        ->assertOk()
        ->assertSee('CRIF reported no accounts.')
        ->assertSee('No inquiries listed in the report.');
});

it('shows a failed request with its safe reason instead of a report', function () {
    $report = CrifReport::factory()->failed(CrifErrorCode::InsufficientBalance)->create();

    $this->get(CrifReportResource::getUrl('view', ['record' => $report]))
        ->assertOk()
        ->assertSee('Insufficient Surepass balance')
        ->assertSee(CrifErrorCode::InsufficientBalance->safeMessage())
        ->assertDontSee('Accounts (');
});

it('records every opening of a report in its history', function () {
    $report = fixtureCrifReport();

    Livewire::test(ViewCrifReport::class, ['record' => $report->getRouteKey()]);

    expect($report->auditLogs()->where('action', 'viewed')->value('user_id'))->toBe($this->admin->id);
});

it('lets a Credit Officer see and request reports but nothing privileged', function () {
    $officer = adminWithRoles(CrifRoles::OFFICER_ROLE);
    $report = fixtureCrifReport();
    $this->actingAs($officer);

    $this->get('/admin/crif-reports')->assertOk();
    $this->get('/admin/crif-dashboard')->assertOk();
    $this->get(CrifReportResource::getUrl('view', ['record' => $report]))->assertOk();
    $this->get('/admin/crif-settings')->assertForbidden();

    Livewire::test(ViewCrifReport::class, ['record' => $report->getRouteKey()])
        ->assertActionVisible('downloadCrifPdf')
        ->assertActionHidden('viewRawCrifJson')
        ->assertActionHidden('fetchFreshCrifReport');
});

it('forbids staff without CRIF permissions from reports and the dashboard', function (string $path) {
    $this->actingAs(adminWithRoles('Marketing'));
    $report = fixtureCrifReport();

    $this->get(str_replace('{report}', $report->public_id, $path))->assertForbidden();
})->with([
    '/admin/crif-reports',
    '/admin/crif-reports/{report}',
    '/admin/crif-dashboard',
]);

test('the policy grants each CRIF ability only through its own permission', function (string $ability, string $permission) {
    $report = CrifReport::factory()->create();
    $user = User::factory()->create();

    expect($user->can($ability, $report))->toBeFalse();
    $user->givePermissionTo($permission);
    expect($user->refresh()->can($ability, $report))->toBeTrue();
})->with([
    ['view', 'View:CrifReport'],
    ['reconcile', 'Reconcile:CrifReport'],
    ['viewRaw', 'ViewRaw:CrifReport'],
    ['downloadPdf', 'DownloadPdf:CrifReport'],
]);

test('nobody may edit or delete a CRIF report attempt', function () {
    $report = CrifReport::factory()->create();

    expect($this->admin->can('update', $report))->toBeTrue() // super_admin passes every gate...
        ->and(CrifReportResource::canEdit($report))->toBeFalse() // ...but the resource offers no edit or delete
        ->and(CrifReportResource::canDelete($report))->toBeFalse()
        ->and(User::factory()->create()->givePermissionTo(CrifRoles::OFFICER)->can('delete', $report))->toBeFalse();
});

it('shows the complete raw JSON only to permitted users and records that it was opened', function () {
    $report = fixtureCrifReport();
    $reviewer = adminWithRoles(CrifRoles::OFFICER_ROLE);
    $reviewer->givePermissionTo('ViewRaw:CrifReport');
    $this->actingAs($reviewer);

    Livewire::test(ViewCrifReport::class, ['record' => $report->getRouteKey()])
        ->mountAction('viewRawCrifJson')
        ->assertMountedActionModalSee('AAAPZ9999Z');

    expect($report->auditLogs()->where('action', 'raw_viewed')->value('user_id'))->toBe($reviewer->id);
});

it('fetches the PDF once, stores it encrypted and serves later downloads from that copy', function () {
    fakeSurepassCrif(Http::response(['success' => true, 'data' => ['credit_report_link' => 'https://files.example.test/crif.pdf']]));
    Http::fake(['files.example.test/*' => Http::response('%PDF-1.7 fixture report', 200)]);
    Storage::fake('local');
    $report = fixtureCrifReport();

    Livewire::test(ViewCrifReport::class, ['record' => $report->getRouteKey()])
        ->callAction('downloadCrifPdf')
        ->assertFileDownloaded('crif-report-XXXXXX999Z-'.now()->format('Ymd').'.pdf');

    $report->refresh();
    expect($report->pdf_path)->not->toBeNull()
        ->and(Storage::disk('local')->get($report->pdf_path))->not->toContain('%PDF');

    Livewire::test(ViewCrifReport::class, ['record' => $report->getRouteKey()])
        ->callAction('downloadCrifPdf')
        ->assertFileDownloaded();

    Http::assertSentCount(2);
    expect($report->auditLogs()->where('action', 'pdf_downloaded')->count())->toBe(1);
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'files.example.test') && $request->hasHeader('Authorization'));
});

it('reports a PDF failure without touching the report', function () {
    fakeSurepassCrif(Http::response(['success' => false, 'message_code' => 'unauthorized'], 401));
    $report = fixtureCrifReport();

    Livewire::test(ViewCrifReport::class, ['record' => $report->getRouteKey()])
        ->callAction('downloadCrifPdf')
        ->assertNotified('Not done');

    expect($report->refresh())->status->toBe(CrifReportStatus::Success)->pdf_error_code->toBe('authentication_failed');
});

it('retries a failed attempt from the report screen', function () {
    fakeSurepassCrif();
    $failed = fixtureCrifReport()->forceFill([
        'status' => CrifReportStatus::Failed, 'error_code' => CrifErrorCode::ProviderUnavailable, 'retryable' => true, 'raw_response' => null, 'score' => null,
    ]);
    $failed->save();

    Livewire::test(ViewCrifReport::class, ['record' => $failed->getRouteKey()])
        ->callAction('retryCrifReport')
        ->assertNotified('CRIF report requested')
        ->assertRedirect();

    expect(CrifReport::query()->where('previous_report_id', $failed->id)->first())
        ->request_type->toBe(CrifRequestType::Retry)
        ->status->toBe(CrifReportStatus::Success);
});

it('reconciles an uncertain attempt from the report screen', function () {
    $report = CrifReport::factory()->awaitingReconciliation()->create();

    Livewire::test(ViewCrifReport::class, ['record' => $report->getRouteKey()])
        ->assertActionHidden('retryCrifReport')
        ->callAction('reconcileCrifReport', data: ['resolution' => 'not_processed', 'note' => 'Surepass usage log shows no charge'])
        ->assertHasNoFormErrors();

    expect($report->refresh())->status->toBe(CrifReportStatus::Failed)->retryable->toBeTrue();
});

it('fetches a CRIF report from the customer page with recorded consent', function () {
    fakeSurepassCrif();
    $customer = Customer::factory()->create(['full_name' => 'Test Fixture', 'phone' => '9000000001']);

    Livewire::test(EditCustomer::class, ['record' => $customer->getRouteKey()])
        ->assertActionHidden('viewExistingCrifReport')
        ->mountAction('fetchCrifReport')
        ->assertSchemaStateSet(['first_name' => 'Test', 'middle_name' => '', 'last_name' => 'Fixture', 'mobile' => '9000000001', 'consent_source' => 'record'])
        ->fillForm([
            'pan' => 'aaapz9999z',
            'consent_method' => 'recorded_call',
            'consented_at' => now()->subHour()->format('Y-m-d H:i:s'),
            'evidence_reference' => 'CALL-2026-10-15-01',
            'attested' => true,
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertNotified('CRIF report requested')
        ->assertRedirect(CrifReportResource::getUrl('view', ['record' => CrifReport::query()->sole()]));

    expect($customer->crifReports()->sole())->status->toBe(CrifReportStatus::Success)->requested_by->toBe($this->admin->id);

    Livewire::test(EditCustomer::class, ['record' => $customer->getRouteKey()])
        ->assertActionVisible('viewExistingCrifReport')
        ->assertActionHidden('fetchCrifReport')
        ->assertActionVisible('fetchFreshCrifReport');
});

it('fetches a CRIF report for a customer picked on the CRIF list', function () {
    fakeSurepassCrif();
    $customer = Customer::factory()->create();

    Livewire::test(ListCrifReports::class)
        ->callAction('fetchCrifReport', data: [
            'customer_id' => $customer->id,
            'first_name' => 'Test',
            'last_name' => 'Fixture',
            'mobile' => '9000000001',
            'pan' => 'AAAPZ9999Z',
            'consent_source' => 'record',
            'consent_method' => 'written_form',
            'consented_at' => now()->subDay()->format('Y-m-d H:i:s'),
            'evidence_reference' => 'FORM-0042',
            'attested' => true,
        ])
        ->assertHasNoFormErrors()
        ->assertNotified('CRIF report requested');

    expect($customer->crifReports()->sole()->status)->toBe(CrifReportStatus::Success);
});

it('validates the request form before anything is sent', function () {
    fakeSurepassCrif();
    $customer = Customer::factory()->create();

    Livewire::test(EditCustomer::class, ['record' => $customer->getRouteKey()])
        ->callAction('fetchCrifReport', data: ['first_name' => 'Test', 'last_name' => 'Fixture', 'mobile' => '123', 'pan' => 'NOTAPAN', 'consent_source' => 'record', 'attested' => false])
        ->assertHasFormErrors(['mobile', 'pan', 'consent_method', 'evidence_reference', 'attested']);

    expect(CrifReport::query()->count())->toBe(0);
    Http::assertNothingSent();
});

it('prefills a request from an application and offers its website consent', function () {
    fakeSurepassCrif();
    $customer = Customer::factory()->create(['full_name' => 'Asha Kumari Rao']);
    $session = JourneySession::factory()->create(['customer_id' => $customer->id, 'phone_number' => '9000000002']);
    $consent = CreditConsent::factory()->create(['journey_session_id' => $session->id, 'pan_number' => 'AAAPZ9999Z']);

    Livewire::test(ViewJourneySession::class, ['record' => $session->getRouteKey()])
        ->mountAction('fetchCrifReport')
        ->assertSchemaStateSet([
            'first_name' => 'Asha',
            'middle_name' => 'Kumari',
            'last_name' => 'Rao',
            'mobile' => '9000000002',
            'pan' => 'AAAPZ9999Z',
            'consent_source' => $consent->id,
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    Http::assertSent(fn ($request) => $request['first_name'] === 'Asha Kumari' && $request['last_name'] === 'Rao');
    expect($session->crifReports()->sole())
        ->customer_id->toBe($customer->id)
        ->credit_consent_id->toBe($consent->id);
});

it('shows the latest CRIF status on the customer list and tab', function () {
    $report = fixtureCrifReport();

    Livewire::test(ListCustomers::class)
        ->assertTableColumnStateSet('latestCrifReport.status', CrifReportStatus::Success, $report->customer);

    Livewire::test(CrifReportsRelationManager::class, ['ownerRecord' => $report->customer, 'pageClass' => EditCustomer::class])
        ->assertCanSeeTableRecords([$report]);
});

it('filters reports by PAN through its hash, case- and space-insensitively', function () {
    $match = fixtureCrifReport();
    $other = CrifReport::factory()->forPan('BBBPZ1111Z')->successful()->create();

    Livewire::test(ListCrifReports::class)
        ->filterTable('pan', ['pan' => ' aaapz 9999z'])
        ->assertCanSeeTableRecords([$match])
        ->assertCanNotSeeTableRecords([$other])
        ->filterTable('status', [CrifReportStatus::Failed->value])
        ->assertCanNotSeeTableRecords([$match, $other]);
});

it('does not read the PAN filter from the address bar', function () {
    $match = fixtureCrifReport();
    $other = CrifReport::factory()->forPan('BBBPZ1111Z')->successful()->create();

    Livewire::withQueryParams(['filters' => ['pan' => ['pan' => 'AAAPZ9999Z']]])
        ->test(ListCrifReports::class)
        ->assertCanSeeTableRecords([$match, $other]);
});

it('counts requests, failures, reuse and blocked duplicates on the dashboard', function () {
    CrifReport::factory()->successful()->create(['times_reused' => 3]);
    CrifReport::factory()->failed(CrifErrorCode::RateLimited)->create();
    CrifReport::factory()->awaitingReconciliation()->create(['duplicates_blocked' => 2]);
    CrifReport::factory()->successful()->create(['request_type' => CrifRequestType::Fresh]);

    $stats = Livewire::test(CrifDashboard::class)->instance()->stats();

    $this->get('/admin/crif-dashboard')
        ->assertOk()
        ->assertSee('Rate limited by Surepass')
        ->assertSee('Surepass timed out');

    expect(collect($stats)->map(fn (array $stat) => $stat['value'])->all())->toMatchArray([
        'Total requests' => 4,
        'Successful' => 2,
        'Failed' => 1,
        'In progress' => 0,
        'Needs reconciliation' => 1,
        'Duplicates prevented' => 2,
        'Reports reused' => 3,
        'Fresh requests' => 1,
    ]);
});

it('saves the reuse, consent and retention settings without ever showing the token', function () {
    config(['services.surepass.token' => 'test-surepass-token']);

    $this->get('/admin/crif-settings')
        ->assertSee('Surepass API token is configured on this server')
        ->assertDontSee('test-surepass-token');

    Livewire::test(CrifSettings::class)
        ->fillForm(['crif_enabled' => true, 'crif_public_score_enabled' => true, 'crif_reuse_days' => 14, 'crif_consent_validity_days' => 7, 'crif_retention_days' => 180, 'crif_alert_email' => 'ops@example.test'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(CrifSettingsStore::enabled())->toBeTrue()
        ->and(CrifSettingsStore::publicScoreEnabled())->toBeTrue()
        ->and(CrifSettingsStore::reuseDays())->toBe(14)
        ->and(CrifSettingsStore::consentValidityDays())->toBe(7)
        ->and(CrifSettingsStore::retentionDays())->toBe(180)
        ->and(CrifSettingsStore::alertEmail())->toBe('ops@example.test')
        ->and(Setting::query()->where('value', 'like', '%test-surepass-token%')->exists())->toBeFalse();
});
