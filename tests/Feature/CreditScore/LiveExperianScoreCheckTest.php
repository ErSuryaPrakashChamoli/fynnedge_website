<?php

use App\Filament\Resources\ExperianCreditRequests\Pages\ListExperianCreditRequests;
use App\Models\Setting;
use App\Models\User;
use App\Modules\CreditBureau\Enums\ConsentMethod;
use App\Modules\CreditBureau\Enums\ConsentStatus;
use App\Modules\CreditBureau\Enums\CreditCheckStatus;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use App\Modules\Customers\Models\Customer;
use App\Modules\Experian\Enums\ExperianErrorCode;
use App\Modules\Experian\Enums\ExperianProduct;
use App\Modules\Experian\Enums\ExperianRequestStatus;
use App\Modules\Experian\Models\ExperianCreditRequest;
use App\Modules\Experian\Services\ExperianSettings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * Real scores on the public Experian page, with Surepass's
 * credit-report-experian faked (and the SMS gateway, for the OTP step).
 */
function liveExperianScorePage(mixed $response = null): void
{
    fakeAreachSms();
    fakeSurepassExperian($response);
    Setting::set(ExperianSettings::PUBLIC_SCORE_ENABLED, true);
}

function checkExperianOnPage(string $mobile = '9000000001', string $bureau = 'experian'): Testable
{
    $component = Livewire::test('credit-score-check', ['bureau' => $bureau])
        ->set('mobileNumber', $mobile)
        ->call('sendOtp');

    return $component
        ->set('otpCode', lastTextedOtp())
        ->call('verifyOtp')
        ->set('firstName', 'Test')
        ->set('lastName', 'Fixture')
        ->set('dateOfBirth', '1990-01-01')
        ->set('panNumber', 'aaapz9999z')
        ->set('consent', true)
        ->call('submitDetails');
}

function surepassExperianCalls(): int
{
    return collect(Http::recorded())->filter(fn (array $pair) => str_contains($pair[0]->url(), '/credit-report-experian/'))->count();
}

it('shows a real Experian score read from the full report, sending only name, PAN, mobile and consent', function () {
    liveExperianScorePage();

    checkExperianOnPage()
        ->assertSet('step', 'result')
        ->assertSet('resultKind', 'live')
        ->assertSet('resultScore', 792)
        ->assertSee('Live score from Experian')
        ->assertDontSee('Demo score');

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/credit-report-experian/fetch-report')
        && $request->data() === ['name' => 'Test Fixture', 'pan' => 'AAAPZ9999Z', 'mobile' => '9000000001', 'consent' => 'Y']);

    $request = ExperianCreditRequest::query()->sole();

    expect(CreditScoreCheck::query()->sole())
        ->provider->toBe('surepass')
        ->gender->toBeNull()
        ->score->toBe(792)
        ->raw_response->toBe(['client_id' => 'credit_report_experian_TESTFIXTURE0001', 'message_code' => 'success', 'source' => 'report', 'experian_request' => $request->public_id]);
});

it('records every paid website check as an Experian request with its full report and the visitor\'s consent', function () {
    liveExperianScorePage();
    $customer = Customer::factory()->create(['phone' => '9000000001']);

    checkExperianOnPage();

    $request = ExperianCreditRequest::query()->sole();
    expect($request)
        ->channel->toBe('website')
        ->product->toBe(ExperianProduct::Report)
        ->status->toBe(ExperianRequestStatus::Success)
        ->score->toBe(792)
        ->customer_id->toBe($customer->id)
        ->requested_by->toBeNull()
        ->provider_client_id->toBe('credit_report_experian_TESTFIXTURE0001')
        ->and($request->raw_response)->toBe(experianFixture())
        ->and($request->consent)->status->toBe(ConsentStatus::Given)->method->toBe(ConsentMethod::Otp)
        ->and($request->consent->purpose)->toBe('Free Experian score check on the website');

    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $this->get('/admin/experian-dashboard')->assertSee('Website visitors (public score page)');
    Livewire::test(ListExperianCreditRequests::class, ['activeTab' => 'reports'])->assertCanSeeTableRecords([$request]);
});

it('records a failed or uncertain website check where admins look for them', function (mixed $stub, ExperianRequestStatus $status, ExperianErrorCode $code) {
    liveExperianScorePage($stub);

    checkExperianOnPage();

    expect(ExperianCreditRequest::query()->sole())->channel->toBe('website')->status->toBe($status)->error_code->toBe($code)->raw_response->toBeNull();
})->with([
    'no record (definite)' => [fn () => Http::response(['success' => false, 'message_code' => 'no_record_found'], 422), ExperianRequestStatus::Failed, ExperianErrorCode::NoRecordFound],
    'server error (maybe charged)' => [fn () => Http::response('', 500), ExperianRequestStatus::ReconciliationRequired, ExperianErrorCode::ProviderError],
]);

it('never asks for gender on the Experian page', function () {
    liveExperianScorePage();

    Livewire::test('credit-score-check', ['bureau' => 'experian'])
        ->assertSee('Your real Experian score, free')
        ->set('mobileNumber', '9000000001')->call('sendOtp')->set('otpCode', lastTextedOtp())->call('verifyOtp')
        ->assertDontSee('needs this to find your credit record.');
});

it('shows the same person their stored Experian score without paying again', function () {
    liveExperianScorePage();
    checkExperianOnPage();
    $this->travel(2)->minutes();

    checkExperianOnPage()->assertSet('resultKind', 'live')->assertSet('resultScore', 792);

    expect(surepassExperianCalls())->toBe(1)
        ->and(ExperianCreditRequest::query()->count())->toBe(1);
});

it('never shows a demo score when the live check fails, and explains the plan to admins only', function () {
    liveExperianScorePage(Http::response(['success' => false, 'message_code' => 'invalid_token', 'message' => 'Your access token is not valid for this API/Scope.'], 401));
    config(['services.surepass.experian_public_score_source' => 'score']);

    checkExperianOnPage()->assertSet('resultKind', 'failed')->assertSet('resultScore', null)
        ->assertSee('We could not fetch your Experian score right now')->assertDontSee('API/Scope')->assertDontSee('Demo score');

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/credit-report-experian/score'));
    expect(CreditScoreCheck::query()->sole()->liveFailure())->toBe(ExperianErrorCode::ApiNotEnabled);
});

it('keeps a labelled demo score when switched off, and CIBIL stays a demo', function () {
    liveExperianScorePage();
    Setting::set(ExperianSettings::PUBLIC_SCORE_ENABLED, false);

    checkExperianOnPage()->assertSet('resultKind', 'demo')->assertSee('Demo score');
    expect(CreditScoreCheck::query()->sole()->provider)->toBe('demo');

    Setting::set(ExperianSettings::PUBLIC_SCORE_ENABLED, true);
    checkExperianOnPage(mobile: '9000000005', bureau: 'cibil')->assertSet('resultKind', 'demo')->assertSee('Demo score');

    expect(surepassExperianCalls())->toBe(0)
        ->and(CreditScoreCheck::query()->where('status', CreditCheckStatus::Completed)->count())->toBe(2);
});
