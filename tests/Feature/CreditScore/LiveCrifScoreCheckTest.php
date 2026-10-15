<?php

use App\Models\Setting;
use App\Modules\CreditBureau\Enums\CreditCheckStatus;
use App\Modules\CreditBureau\Services\CrifSettings;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * Turns on real scores for the public CRIF page with Surepass's score endpoint
 * faked (and the SMS gateway, for the OTP step).
 */
function liveCrifScorePage(mixed $scoreResponse = null): void
{
    fakeAreachSms();
    fakeSurepassCrif($scoreResponse ?? Http::response([
        'success' => true,
        'status_code' => 200,
        'message_code' => 'success',
        'data' => ['client_id' => 'credit_report_crif_score_TEST', 'credit_score' => '742', 'pan' => 'AAAPZ9999Z', 'mobile' => '9000000001'],
    ]));
    Setting::set(CrifSettings::PUBLIC_SCORE_ENABLED, true);
}

function checkScoreOnPage(string $bureau = 'crif', string $mobile = '9000000001', string $pan = 'aaapz9999z', string $name = 'Test Fixture'): Testable
{
    $component = Livewire::test('credit-score-check', ['bureau' => $bureau])
        ->set('mobileNumber', $mobile)
        ->call('sendOtp');

    return $component
        ->set('otpCode', lastTextedOtp())
        ->call('verifyOtp')
        ->set('fullName', $name)
        ->set('dateOfBirth', '1990-01-01')
        ->set('panNumber', $pan)
        ->set('consent', true)
        ->call('submitDetails');
}

function surepassScoreCalls(): int
{
    return collect(Http::recorded())
        ->filter(fn (array $pair) => str_ends_with($pair[0]->url(), '/credit-report-crif/score'))
        ->count();
}

it('shows a real CRIF score from Surepass when switched on', function () {
    liveCrifScorePage();

    checkScoreOnPage()
        ->assertSet('step', 'result')
        ->assertSet('resultKind', 'live')
        ->assertSet('resultScore', 742)
        ->assertSee('Live score from CRIF High Mark')
        ->assertDontSee('Demo score');

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/credit-report-crif/score')
        && $request->data() === ['first_name' => 'Test', 'last_name' => 'Fixture', 'mobile' => '9000000001', 'pan' => 'AAAPZ9999Z', 'consent' => 'Y']);

    expect(CreditScoreCheck::query()->sole())
        ->provider->toBe('surepass')
        ->status->toBe(CreditCheckStatus::Completed)
        ->score->toBe(742)
        ->raw_response->toBe(['client_id' => 'credit_report_crif_score_TEST', 'message_code' => 'success']);
});

it('tells the visitor up front that the CRIF score is real', function () {
    liveCrifScorePage();

    Livewire::test('credit-score-check', ['bureau' => 'crif'])
        ->assertSee('Your real CRIF score, free')
        ->assertDontSee('Demo mode');
});

it('shows the same person their stored score within the reuse period without paying again', function () {
    liveCrifScorePage();
    checkScoreOnPage();
    $this->travel(2)->minutes();

    checkScoreOnPage()->assertSet('resultKind', 'live')->assertSet('resultScore', 742);

    expect(surepassScoreCalls())->toBe(1)
        ->and(CreditScoreCheck::query()->latest('id')->first()->raw_response)->toHaveKey('reused_from');
});

it('pays for a new check when the same PAN arrives with a different verified mobile', function () {
    liveCrifScorePage();

    checkScoreOnPage(mobile: '9000000001');
    checkScoreOnPage(mobile: '9000000002');

    expect(surepassScoreCalls())->toBe(2);
});

it('pays for a new check once the reuse period has passed', function () {
    liveCrifScorePage();
    Setting::set(CrifSettings::REUSE_DAYS, 30);
    checkScoreOnPage();
    $this->travel(31)->days();

    checkScoreOnPage();

    expect(surepassScoreCalls())->toBe(2);
});

it('explains when CRIF holds no score instead of inventing one', function () {
    liveCrifScorePage(Http::response(['success' => true, 'data' => ['credit_score' => '-1']]));

    checkScoreOnPage()
        ->assertSet('resultKind', 'no_score')
        ->assertSet('resultScore', null)
        ->assertSee('No score yet');
});

it('explains a rejected check without showing provider text', function () {
    liveCrifScorePage(Http::response(['success' => false, 'message_code' => 'invalid_pan', 'message' => 'Provider internal detail'], 422));

    checkScoreOnPage()
        ->assertSet('resultKind', 'failed')
        ->assertSee('CRIF could not find a credit record matching these details')
        ->assertDontSee('Provider internal detail');

    expect(CreditScoreCheck::query()->sole())
        ->status->toBe(CreditCheckStatus::Failed)
        ->score->toBeNull();
});

it('holds an uncertain check for an hour instead of re-sending it', function () {
    // A stub that throws is never in Http::recorded(), so count attempts here.
    $attempts = 0;
    liveCrifScorePage(function () use (&$attempts) {
        $attempts++;

        throw new ConnectionException('cURL error 28: Operation timed out after 60001 milliseconds');
    });

    checkScoreOnPage()->assertSet('resultKind', 'pending')->assertSee('Still checking');
    $this->travel(2)->minutes();
    checkScoreOnPage()->assertSet('resultKind', 'pending');

    expect($attempts)->toBe(1);

    $this->travel(61)->minutes();
    checkScoreOnPage();

    expect($attempts)->toBe(2);
});

it('asks for a first and last name before paying for a check', function () {
    liveCrifScorePage();

    checkScoreOnPage(name: 'Madonna')
        ->assertSet('step', 'details')
        ->assertHasErrors('fullName');

    expect(surepassScoreCalls())->toBe(0)
        ->and(CreditScoreCheck::query()->count())->toBe(0);
});

it('keeps showing a demo score when switched off or without a token', function (Closure $disable) {
    liveCrifScorePage();
    $disable();

    checkScoreOnPage()
        ->assertSet('resultKind', 'demo')
        ->assertSee('Demo score');

    expect(surepassScoreCalls())->toBe(0)
        ->and(CreditScoreCheck::query()->sole()->provider)->toBe('demo');
})->with([
    'switched off' => [fn () => Setting::set(CrifSettings::PUBLIC_SCORE_ENABLED, false)],
    'no token' => [fn () => config(['services.surepass.token' => null])],
]);

it('keeps the other bureaus on demo scores while CRIF is live', function () {
    liveCrifScorePage();

    checkScoreOnPage(bureau: 'cibil')->assertSet('resultKind', 'demo');

    expect(surepassScoreCalls())->toBe(0);
});
