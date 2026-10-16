<?php

use App\Models\Setting;
use App\Modules\CreditBureau\Enums\CreditCheckStatus;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use App\Modules\Experian\Enums\ExperianErrorCode;
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

    expect(CreditScoreCheck::query()->sole())
        ->provider->toBe('surepass')
        ->gender->toBeNull()
        ->score->toBe(792)
        ->raw_response->toBe(['client_id' => 'credit_report_experian_TESTFIXTURE0001', 'message_code' => 'success', 'source' => 'report'])
        ->and(ExperianCreditRequest::query()->count())->toBe(0);
});

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

    expect(surepassExperianCalls())->toBe(1);
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
