<?php

use App\Models\Setting;
use App\Modules\CreditBureau\Enums\CreditCheckStatus;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use App\Modules\Equifax\Enums\EquifaxErrorCode;
use App\Modules\Equifax\Models\EquifaxCreditRequest;
use App\Modules\Equifax\Services\EquifaxSettings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * Real scores on the public Equifax page, with Surepass's credit-report-v2
 * faked (and the SMS gateway, for the OTP step).
 */
function liveEquifaxScorePage(mixed $response = null): void
{
    fakeAreachSms();
    fakeSurepassEquifax($response);
    Setting::set(EquifaxSettings::PUBLIC_SCORE_ENABLED, true);
}

function checkEquifaxOnPage(string $mobile = '9000000001', string $gender = 'male', string $bureau = 'equifax'): Testable
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
        ->set('gender', $gender)
        ->set('consent', true)
        ->call('submitDetails');
}

function surepassEquifaxCalls(): int
{
    return collect(Http::recorded())->filter(fn (array $pair) => str_contains($pair[0]->url(), '/credit-report-v2/'))->count();
}

it('shows a real Equifax score read from the full report, with the Equifax payload', function () {
    liveEquifaxScorePage();

    checkEquifaxOnPage(gender: 'female')
        ->assertSet('step', 'result')
        ->assertSet('resultKind', 'live')
        ->assertSet('resultScore', 761)
        ->assertSee('Live score from Equifax')
        ->assertDontSee('Demo score');

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/credit-report-v2/fetch-report')
        && $request->data() === ['name' => 'Test Fixture', 'id_number' => 'AAAPZ9999Z', 'id_type' => 'pan', 'mobile' => '9000000001', 'consent' => 'Y', 'gender' => 'female']);

    expect(CreditScoreCheck::query()->sole())
        ->provider->toBe('surepass')
        ->gender->toBe('female')
        ->score->toBe(761)
        ->raw_response->toBe(['client_id' => 'credit_report_v2_TESTFIXTURE0001', 'message_code' => 'success', 'source' => 'report'])
        ->and(EquifaxCreditRequest::query()->count())->toBe(0);
});

it('asks for gender only on the live Equifax page, and requires it there', function () {
    liveEquifaxScorePage();

    $component = Livewire::test('credit-score-check', ['bureau' => 'equifax'])->assertSee('Your real Equifax score, free');
    $component->set('mobileNumber', '9000000001')->call('sendOtp')->set('otpCode', lastTextedOtp())->call('verifyOtp')
        ->assertSee('Equifax needs this to find your credit record.');

    checkEquifaxOnPage(mobile: '9000000002', gender: '')->assertSet('step', 'details')->assertHasErrors('gender');
    expect(surepassEquifaxCalls())->toBe(0);

    Livewire::test('credit-score-check', ['bureau' => 'experian'])
        ->set('mobileNumber', '9000000003')->call('sendOtp')->set('otpCode', lastTextedOtp())->call('verifyOtp')
        ->assertDontSee('Equifax needs this to find your credit record.');
});

it('shows the same person their stored Equifax score without paying again', function () {
    liveEquifaxScorePage();
    checkEquifaxOnPage();
    $this->travel(2)->minutes();

    checkEquifaxOnPage()->assertSet('resultKind', 'live')->assertSet('resultScore', 761);

    expect(surepassEquifaxCalls())->toBe(1);
});

it('reads the score endpoint when configured and explains a plan without it to admins only', function () {
    liveEquifaxScorePage(Http::response(['success' => false, 'message_code' => 'invalid_token', 'message' => 'Your access token is not valid for this API/Scope.'], 401));
    config(['services.surepass.equifax_public_score_source' => 'score']);

    checkEquifaxOnPage()->assertSet('resultKind', 'failed')->assertSee('We could not fetch your Equifax score right now')->assertDontSee('API/Scope');

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/credit-report-v2/score'));
    expect(CreditScoreCheck::query()->sole()->liveFailure())->toBe(EquifaxErrorCode::ApiNotEnabled);
});

it('keeps the demo score when switched off, and CRIF and CIBIL are unaffected', function () {
    liveEquifaxScorePage();
    Setting::set(EquifaxSettings::PUBLIC_SCORE_ENABLED, false);

    checkEquifaxOnPage()->assertSet('resultKind', 'demo')->assertSee('Demo score');
    expect(CreditScoreCheck::query()->sole()->provider)->toBe('demo');

    Setting::set(EquifaxSettings::PUBLIC_SCORE_ENABLED, true);
    checkEquifaxOnPage(mobile: '9000000005', bureau: 'cibil')->assertSet('resultKind', 'demo');

    expect(surepassEquifaxCalls())->toBe(0)
        ->and(CreditScoreCheck::query()->where('status', CreditCheckStatus::Completed)->count())->toBe(2);
});
