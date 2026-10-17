<?php

use App\Models\Setting;
use App\Modules\Cibil\Enums\CibilErrorCode;
use App\Modules\Cibil\Enums\CibilProduct;
use App\Modules\Cibil\Enums\CibilRequestStatus;
use App\Modules\Cibil\Models\CibilCreditRequest;
use App\Modules\Cibil\Services\CibilSettings;
use App\Modules\CreditScore\Enums\BureauName;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * The public CIBIL page answered by the CIBIL bureau itself (CIBIL Settings →
 * "Fetch Experian instead of CIBIL" off), with Surepass's credit-report-cibil
 * faked (and the SMS gateway, for the OTP step).
 */
function liveCibilBureauPage(mixed $response = null): void
{
    fakeAreachSms();
    fakeSurepassCibil($response);
    Setting::set(CibilSettings::ENABLED, false);
    Setting::set(CibilSettings::PUBLIC_SCORE_ENABLED, true);
    Setting::set(CibilSettings::PUBLIC_SCORE_USE_EXPERIAN, false);
}

function checkCibilBureauOnPage(string $mobile = '9000000001', string $gender = 'male'): Testable
{
    return Livewire::test('credit-score-check', ['bureau' => 'cibil'])
        ->set('mobileNumber', $mobile)
        ->call('sendOtp')
        ->set('otpCode', lastTextedOtp())
        ->call('verifyOtp')
        ->set('fullName', 'Test Fixture')
        ->set('dateOfBirth', '1990-01-01')
        ->set('panNumber', 'aaapz9999z')
        ->set('gender', $gender)
        ->set('consent', true)
        ->call('submitDetails');
}

function surepassCibilCalls(): int
{
    return collect(Http::recorded())->filter(fn (array $pair) => str_contains($pair[0]->url(), '/credit-report-cibil/'))->count();
}

it('shows a genuine CIBIL score from the CIBIL bureau, sending its documented body', function () {
    liveCibilBureauPage();

    checkCibilBureauOnPage(gender: 'female')
        ->assertSet('resultKind', 'live')
        ->assertSet('resultScore', 750)
        ->assertSee('Live score from CIBIL')
        ->assertSee('Your CIBIL score')
        ->assertDontSee('Demo score')
        ->assertDontSee("isn't connected yet");

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/credit-report-cibil/fetch-report')
        && $request->hasHeader('Authorization', 'Bearer test-cibil-token')
        && $request->data() === ['mobile' => '9000000001', 'pan' => 'AAAPZ9999Z', 'name' => 'Test Fixture', 'gender' => 'female', 'consent' => 'Y']);

    $request = CibilCreditRequest::query()->sole();
    $check = CreditScoreCheck::query()->sole();

    expect($check)
        ->bureau->toBe(BureauName::Cibil)
        ->provider->toBe('surepass')
        ->gender->toBe('female')
        ->score->toBe(750)
        ->raw_response->toBe(['client_id' => 'credit_report_cibil_TESTFIXTURE0001', 'message_code' => 'success', 'source' => 'report', 'cibil_request' => $request->public_id])
        ->and($check->sourceBureau())->toBe(BureauName::Cibil)
        ->and($request)->channel->toBe('website')->product->toBe(CibilProduct::Report)->status->toBe(CibilRequestStatus::Success)->score->toBe(750)
        ->and($request->raw_response)->toBe(cibilFixture());
});

it('asks for gender on the CIBIL page only while CIBIL itself answers it', function () {
    liveCibilBureauPage();

    Livewire::test('credit-score-check', ['bureau' => 'cibil'])
        ->set('mobileNumber', '9000000001')->call('sendOtp')->set('otpCode', lastTextedOtp())->call('verifyOtp')
        ->assertSee('CIBIL needs this to find your credit record.');

    checkCibilBureauOnPage(mobile: '9000000002', gender: '')->assertSet('step', 'details')->assertHasErrors('gender');
    expect(surepassCibilCalls())->toBe(0);

    Setting::set(CibilSettings::PUBLIC_SCORE_USE_EXPERIAN, true);
    config(['services.surepass.token' => 'test-surepass-token']);
    Livewire::test('credit-score-check', ['bureau' => 'cibil'])
        ->set('mobileNumber', '9000000003')->call('sendOtp')->set('otpCode', lastTextedOtp())->call('verifyOtp')
        ->assertDontSee('needs this to find your credit record.');
});

it('shows the same person their stored CIBIL score without paying again', function () {
    liveCibilBureauPage();
    checkCibilBureauOnPage();
    $this->travel(2)->minutes();

    checkCibilBureauOnPage()->assertSet('resultKind', 'live')->assertSet('resultScore', 750);

    expect(surepassCibilCalls())->toBe(1)
        ->and(CibilCreditRequest::query()->count())->toBe(1);
});

it('never shows a demo score when CIBIL fails, and records why for admins', function () {
    liveCibilBureauPage(Http::response(['success' => false, 'message_code' => 'no_record_found'], 422));

    checkCibilBureauOnPage()
        ->assertSet('resultKind', 'failed')
        ->assertSet('resultScore', null)
        ->assertSee('CIBIL could not find a credit record matching these details')
        ->assertDontSee('Demo score');

    expect(CreditScoreCheck::query()->sole()->liveFailure())->toBe(CibilErrorCode::NoRecordFound)
        ->and(CibilCreditRequest::query()->sole())->status->toBe(CibilRequestStatus::Failed)->error_code->toBe(CibilErrorCode::NoRecordFound);
});

it('keeps the labelled demo without the CIBIL token, never borrowing another bureau token', function () {
    liveCibilBureauPage();
    config(['services.surepass.cibil_token' => null, 'services.surepass.token' => 'shared-token']);

    checkCibilBureauOnPage()->assertSet('resultKind', 'demo')->assertSee('Demo score');

    expect(surepassCibilCalls())->toBe(0)
        ->and(CreditScoreCheck::query()->sole()->provider)->toBe('demo');
});

it('takes the CIBIL page\'s bureau from the admin switch alone, never from which tokens are set', function () {
    liveCibilBureauPage();
    Setting::set(CibilSettings::PUBLIC_SCORE_USE_EXPERIAN, null);

    expect(CibilSettings::source())->toBe('cibil')->and(CibilSettings::isStandIn())->toBeFalse();
    checkCibilBureauOnPage()->assertSet('resultKind', 'live')->assertSee('Your CIBIL score');
    expect(surepassCibilCalls())->toBe(1);

    Setting::set(CibilSettings::PUBLIC_SCORE_USE_EXPERIAN, true);
    expect(CibilSettings::source())->toBe('experian')->and(CibilSettings::isStandIn())->toBeTrue()
        ->and(CibilSettings::sourceLabel())->toBe('Experian');

    Setting::set(CibilSettings::PUBLIC_SCORE_USE_EXPERIAN, false);
    config(['services.surepass.cibil_token' => null, 'services.surepass.token' => 'shared-token']);
    expect(CibilSettings::source())->toBe('cibil')->and(CibilSettings::publicScoreLive())->toBeFalse();
});
