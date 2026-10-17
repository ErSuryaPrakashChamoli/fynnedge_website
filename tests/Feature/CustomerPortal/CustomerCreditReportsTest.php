<?php

use App\Models\AuditLog;
use App\Models\Setting;
use App\Modules\CreditBureau\Enums\CreditCheckStatus;
use App\Modules\CreditScore\Enums\BureauName;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use App\Modules\Customers\DataTransferObjects\RefreshEligibility;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Services\CreditReportView;
use App\Modules\Customers\Services\CustomerCreditReports;
use App\Modules\Equifax\Services\EquifaxSettings;
use App\Modules\Experian\Models\ExperianCreditRequest;
use App\Modules\Experian\Services\ExperianSettings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * A genuine Experian check saved to $customer, with its full report held on a
 * successful Experian request — what a live public check leaves behind.
 *
 * @param  array<string, mixed>  $overrides
 */
function savedExperianReport(Customer $customer, array $overrides = []): CreditScoreCheck
{
    $request = ExperianCreditRequest::factory()->successful(experianFixture(), 792)->create([
        'customer_id' => $customer->id,
        'channel' => 'website',
        'requested_by' => null,
    ]);

    return CreditScoreCheck::factory()->create([
        'customer_id' => $customer->id,
        'bureau' => BureauName::Experian,
        'mobile_number' => $customer->verified_mobile,
        'pan_number' => 'AAAPZ9999Z',
        'provider' => CreditScoreCheck::LIVE_PROVIDER,
        'status' => CreditCheckStatus::Completed,
        'score' => 792,
        'raw_response' => ['client_id' => 'credit_report_experian_TESTFIXTURE0001', 'message_code' => 'success', 'source' => 'report', 'experian_request' => $request->public_id],
        ...$overrides,
    ]);
}

beforeEach(function () {
    // No fake: any request to a bureau would throw, so a passing test made none.
    Http::preventStrayRequests();
    $this->customer = Customer::factory()->portalAccount('9000000001')->create();
});

it('lists only the customer\'s own genuine reports, each with its bureau', function () {
    $own = savedExperianReport($this->customer);
    $demo = CreditScoreCheck::factory()->create(['customer_id' => $this->customer->id, 'provider' => 'demo', 'score' => 555]);
    $someoneElses = savedExperianReport(Customer::factory()->portalAccount('9000000002')->create(), ['score' => 611]);

    $this->actingAs($this->customer, 'customer')
        ->get(route('portal.reports.index'))
        ->assertOk()
        ->assertSee(route('portal.reports.show', $own->public_id))
        ->assertSee('Experian')
        ->assertDontSee(route('portal.reports.show', $demo->public_id))
        ->assertDontSee(route('portal.reports.show', $someoneElses->public_id));
});

it('shows a readable report built from the bureau\'s answer, without raw data or full identifiers', function () {
    $check = savedExperianReport($this->customer);

    $this->actingAs($this->customer, 'customer')
        ->get(route('portal.reports.show', $check->public_id))
        ->assertOk()
        ->assertSee(['792', 'Personal information reported', 'Credit accounts', 'Fixture Bank', 'Payment history', 'Recent credit enquiries'])
        ->assertSee('XXXXXX999Z')
        ->assertDontSee('AAAPZ9999Z')
        ->assertDontSee('FIXTUREACC1234')
        ->assertDontSee('credit_report_experian_TESTFIXTURE0001')
        ->assertDontSee('CAIS_Account_DETAILS');

    Http::assertNothingSent();
});

it('records each report view in the bureau request\'s history without a staff user', function () {
    $check = savedExperianReport($this->customer);

    $this->actingAs($this->customer, 'customer')->get(route('portal.reports.show', $check->public_id))->assertOk();

    $log = AuditLog::query()->where('action', 'viewed_by_customer')->sole();
    expect($log->user_id)->toBeNull()
        ->and($log->changes['customer']['new'])->toBe((string) $this->customer->public_id);
});

it('answers 404 for another customer\'s report, a demo check or an unknown id', function (Closure $makeCheck) {
    $publicId = $makeCheck($this->customer);

    $this->actingAs($this->customer, 'customer')
        ->get(route('portal.reports.show', $publicId))
        ->assertNotFound();
})->with([
    'another customer' => [fn () => savedExperianReport(Customer::factory()->portalAccount('9000000002')->create())->public_id],
    'demo check' => [fn (Customer $customer) => CreditScoreCheck::factory()->create(['customer_id' => $customer->id, 'provider' => 'demo'])->public_id],
    'unknown id' => [fn () => '0b9e5c1e-0000-4000-8000-000000000000'],
]);

it('says when only the score was saved, rather than showing an empty report', function () {
    $check = CreditScoreCheck::factory()->create([
        'customer_id' => $this->customer->id,
        'bureau' => BureauName::Crif,
        'provider' => CreditScoreCheck::LIVE_PROVIDER,
        'score' => 701,
        'raw_response' => ['client_id' => 'crif_x', 'source' => 'report'],
    ]);

    $this->actingAs($this->customer, 'customer')
        ->get(route('portal.reports.show', $check->public_id))
        ->assertOk()
        ->assertSee('Only the score from this check was saved')
        ->assertDontSee('Credit accounts');
});

it('serves a stored PDF only to its owner, from private encrypted storage', function () {
    Storage::fake('local');
    $check = savedExperianReport($this->customer);
    $request = $check->bureauRequest();
    Storage::disk('local')->put('experian-reports/test.pdf.enc', Crypt::encryptString('%PDF-1.4 synthetic'));
    $request->update(['pdf_path' => 'experian-reports/test.pdf.enc', 'pdf_fetched_at' => now()]);

    $response = $this->actingAs($this->customer, 'customer')->get(route('portal.reports.pdf', $check->public_id));
    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($response->getContent())->toBe('%PDF-1.4 synthetic');

    $other = Customer::factory()->portalAccount('9000000002')->create();
    $this->actingAs($other, 'customer')->get(route('portal.reports.pdf', $check->public_id))->assertNotFound();
});

it('never fetches a provider PDF link for a customer', function () {
    $check = savedExperianReport($this->customer);

    $this->actingAs($this->customer, 'customer')
        ->get(route('portal.reports.pdf', $check->public_id))
        ->assertNotFound();

    Http::assertNothingSent();
});

it('downloads a saved report as a PDF file, offered on the list and the report page, without contacting a bureau', function () {
    $check = savedExperianReport($this->customer);
    $download = route('portal.reports.download', $check->public_id);

    $this->actingAs($this->customer, 'customer')->get(route('portal.reports.index'))->assertSee($download);
    $this->actingAs($this->customer, 'customer')->get(route('portal.reports.show', $check->public_id))->assertSee($download);

    $response = $this->actingAs($this->customer, 'customer')->get($download);

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment; filename="fynnedge-experian-credit-report-')
        ->and($response->getContent())->toStartWith('%PDF-')
        ->and(AuditLog::query()->where('action', 'report_pdf_downloaded_by_customer')->sole()->user_id)->toBeNull();
    Http::assertNothingSent();
});

it('fills the PDF with the saved report, identifiers masked', function () {
    $check = savedExperianReport($this->customer);

    $html = view('portal.credit-reports.pdf', ['customer' => $this->customer, 'check' => $check, 'report' => CreditReportView::for($check)])->render();

    expect($html)->toContain('792', 'Excellent', 'Credit accounts', 'Fixture Bank', 'Payment history', 'XXXXXX999Z')
        ->not->toContain('AAAPZ9999Z')
        ->not->toContain('FIXTUREACC1234');
});

it('offers no PDF download for a check the bureau did not answer, or anyone else\'s report', function (Closure $makeCheck) {
    $check = $makeCheck($this->customer);

    $this->actingAs($this->customer, 'customer')
        ->get(route('portal.reports.download', $check->public_id))
        ->assertNotFound();
})->with([
    'failed check' => [fn (Customer $customer) => savedExperianReport($customer, ['status' => CreditCheckStatus::Failed, 'score' => null])],
    'another customer' => [fn () => savedExperianReport(Customer::factory()->portalAccount('9000000002')->create())],
    'demo check' => [fn (Customer $customer) => CreditScoreCheck::factory()->create(['customer_id' => $customer->id, 'provider' => 'demo'])],
]);

it('keeps a refresh closed until the regeneration limit allows a new report', function () {
    Setting::set(ExperianSettings::PUBLIC_SCORE_ENABLED, true);
    config(['services.surepass.token' => 'test-surepass-token']);
    $completedAt = now()->subDays(40);
    savedExperianReport($this->customer, ['completed_at' => $completedAt]);

    $this->actingAs($this->customer, 'customer')
        ->get(route('portal.credit-score.refresh', 'experian'))
        ->assertOk()
        ->assertSee('Next refresh available on '.$completedAt->copy()->addMonths(3)->translatedFormat('d M Y'))
        ->assertDontSeeLivewire('credit-score-check');
});

it('applies the limit across bureaus: a recent Experian report closes the Equifax refresh too', function () {
    Setting::set(ExperianSettings::PUBLIC_SCORE_ENABLED, true);
    Setting::set(EquifaxSettings::PUBLIC_SCORE_ENABLED, true);
    config(['services.surepass.token' => 'test-surepass-token']);
    savedExperianReport($this->customer, ['completed_at' => now()->subDays(40)]);

    $this->actingAs($this->customer, 'customer')
        ->get(route('portal.credit-score'))
        ->assertOk()
        ->assertSee('Next refresh available on '.now()->subDays(40)->addMonths(3)->translatedFormat('d M Y'));

    expect(app(CustomerCreditReports::class)->refreshEligibility($this->customer, BureauName::Equifax)->state)
        ->toBe(RefreshEligibility::TOO_SOON);
});

it('opens a refresh once the regeneration limit has passed', function () {
    Setting::set(ExperianSettings::PUBLIC_SCORE_ENABLED, true);
    config(['services.surepass.token' => 'test-surepass-token']);
    savedExperianReport($this->customer, ['completed_at' => now()->subMonths(3)->subDay()]);

    $this->actingAs($this->customer, 'customer')
        ->get(route('portal.credit-score.refresh', 'experian'))
        ->assertOk()
        ->assertSeeLivewire('credit-score-check');
});

it('blocks a refresh while an earlier check still awaits the bureau', function () {
    Setting::set(ExperianSettings::PUBLIC_SCORE_ENABLED, true);
    config(['services.surepass.token' => 'test-surepass-token']);
    savedExperianReport($this->customer, ['status' => CreditCheckStatus::Pending, 'score' => null, 'completed_at' => null]);

    $this->actingAs($this->customer, 'customer')
        ->get(route('portal.credit-score'))
        ->assertOk()
        ->assertSee('Your last check is still waiting for the bureau');
});

it('offers no refresh for a bureau whose live scores are switched off', function () {
    savedExperianReport($this->customer, ['completed_at' => now()->subDays(40)]);

    $this->actingAs($this->customer, 'customer')
        ->get(route('portal.credit-score.refresh', 'experian'))
        ->assertOk()
        ->assertSee('Live scores from this bureau are not available right now.')
        ->assertDontSeeLivewire('credit-score-check');
});

it('keeps the last successful report when a refresh fails, and adds the failure to the history', function () {
    fakeAreachSms();
    fakeSurepassExperian(Http::response('', 500));
    Setting::set(ExperianSettings::PUBLIC_SCORE_ENABLED, true);
    $previous = savedExperianReport($this->customer, ['completed_at' => now()->subMonths(3)->subDay()]);
    Auth::guard('customer')->login($this->customer);

    Livewire::test('credit-score-check', ['bureau' => 'experian', 'context' => 'portal'])
        ->call('sendOtp')->set('otpCode', lastTextedOtp())->call('verifyOtp')
        ->assertSet('panNumber', 'AAAPZ9999Z')
        ->set('consent', true)
        ->call('submitDetails')
        ->assertSet('resultKind', 'pending')
        ->assertSet('resultScore', null);

    expect($this->customer->creditScoreChecks()->count())->toBe(2);

    $this->get(route('portal.credit-score'))->assertOk()->assertSee(route('portal.reports.show', $previous->public_id))->assertSee('Awaiting bureau');
    $this->get(route('portal.reports.show', $previous->public_id))->assertOk()->assertSee('792');
});

it('adds a successful refresh as a new report and keeps the earlier one', function () {
    fakeAreachSms();
    fakeSurepassExperian();
    Setting::set(ExperianSettings::PUBLIC_SCORE_ENABLED, true);
    $previous = savedExperianReport($this->customer, ['completed_at' => now()->subMonths(3)->subDay(), 'score' => 700]);
    Auth::guard('customer')->login($this->customer);

    Livewire::test('credit-score-check', ['bureau' => 'experian', 'context' => 'portal'])
        ->call('sendOtp')->set('otpCode', lastTextedOtp())->call('verifyOtp')
        ->set('consent', true)
        ->call('submitDetails')
        ->assertSet('resultKind', 'live')
        ->assertSee(route('portal.reports.show', $this->customer->creditScoreChecks()->latest('id')->first()->public_id));

    expect($this->customer->creditScoreChecks()->count())->toBe(2)
        ->and($previous->fresh()->score)->toBe(700);
});

it('needs fresh consent for a refresh — signing in is not consent', function () {
    fakeAreachSms();
    fakeSurepassExperian();
    Setting::set(ExperianSettings::PUBLIC_SCORE_ENABLED, true);
    Auth::guard('customer')->login($this->customer);

    Livewire::test('credit-score-check', ['bureau' => 'experian', 'context' => 'portal'])
        ->call('sendOtp')->set('otpCode', lastTextedOtp())->call('verifyOtp')
        ->set('fullName', 'Test Fixture')->set('dateOfBirth', '1990-01-01')->set('panNumber', 'AAAPZ9999Z')
        ->call('submitDetails')
        ->assertHasErrors(['consent' => 'Please provide consent to continue.']);

    expect(collect(Http::recorded())->filter(fn (array $pair): bool => str_contains($pair[0]->url(), 'surepass')))->toBeEmpty();
});

it('shows the dashboard from saved data without contacting a bureau', function () {
    savedExperianReport($this->customer);

    $this->actingAs($this->customer, 'customer')
        ->get(route('portal.dashboard'))
        ->assertOk()
        ->assertSee(['Latest credit score', '792', 'Experian', 'Profile completion', 'Active loan applications']);

    Http::assertNothingSent();
});

it('shows helpful empty states to a new customer', function () {
    $this->actingAs($this->customer, 'customer')
        ->get(route('portal.dashboard'))
        ->assertOk()
        ->assertSee(['No credit score saved yet', 'No loan applications yet', 'No credit reports yet']);
});
