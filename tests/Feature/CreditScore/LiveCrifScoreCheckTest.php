<?php

use App\Filament\Resources\CreditScoreChecks\CreditScoreCheckResource;
use App\Models\Setting;
use App\Models\User;
use App\Modules\CreditBureau\Enums\CreditCheckStatus;
use App\Modules\CreditBureau\Enums\CrifErrorCode;
use App\Modules\CreditBureau\Services\CrifSettings;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * Turns on real scores for the public CRIF page with Surepass faked (and the
 * SMS gateway, for the OTP step). By default the page reads the score from the
 * full-report endpoint, so the default answer is the full report fixture.
 */
function liveCrifScorePage(mixed $response = null): void
{
    fakeAreachSms();
    fakeSurepassCrif($response ?? Http::response(crifReportFixture()));
    Setting::set(CrifSettings::PUBLIC_SCORE_ENABLED, true);
}

/**
 * The full report fixture with a different score — CRIF sends it both as
 * data.credit_score and as the SCORES block.
 *
 * @return array<string, mixed>
 */
function crifReportWithScore(string $score): array
{
    $body = crifReportFixture();
    $body['data']['credit_score'] = $score;
    $body['data']['credit_report']['SCORES']['SCORE']['SCORE-VALUE'] = $score;

    return $body;
}

function checkScoreOnPage(string $bureau = 'crif', string $mobile = '9000000001', string $pan = 'aaapz9999z', string $firstName = 'Test', string $middleName = '', string $lastName = 'Fixture'): Testable
{
    $component = Livewire::test('credit-score-check', ['bureau' => $bureau])
        ->set('mobileNumber', $mobile)
        ->call('sendOtp');

    return $component
        ->set('otpCode', lastTextedOtp())
        ->call('verifyOtp')
        ->set('firstName', $firstName)
        ->set('middleName', $middleName)
        ->set('lastName', $lastName)
        ->set('dateOfBirth', '1990-01-01')
        ->set('panNumber', $pan)
        ->set('consent', true)
        ->call('submitDetails');
}

/**
 * Paid Surepass CRIF calls made so far, on any CRIF endpoint.
 */
function surepassScoreCalls(): int
{
    return collect(Http::recorded())
        ->filter(fn (array $pair) => str_contains($pair[0]->url(), '/credit-report-crif/'))
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

    // The score-only endpoint is not in every Surepass plan; fetch-report is.
    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/credit-report-crif/fetch-report')
        && $request->data() === ['first_name' => 'Test', 'last_name' => 'Fixture', 'mobile' => '9000000001', 'pan' => 'AAAPZ9999Z', 'consent' => 'Y']);

    expect(CreditScoreCheck::query()->sole())
        ->provider->toBe('surepass')
        ->status->toBe(CreditCheckStatus::Completed)
        ->score->toBe(742)
        ->raw_response->toBe(['client_id' => 'credit_report_crif_TESTFIXTURE0001', 'message_code' => 'success', 'source' => 'report']);
});

it('sends the middle name inside first_name and keeps a two-word surname whole', function () {
    liveCrifScorePage();

    checkScoreOnPage(firstName: 'surya', middleName: 'PRAKASH', lastName: 'sen gupta')->assertSet('resultKind', 'live');

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/credit-report-crif/')
        && $request['first_name'] === 'Surya Prakash' && $request['last_name'] === 'Sen Gupta');

    expect(CreditScoreCheck::query()->sole())
        ->full_name->toBe('Surya Prakash Sen Gupta')
        ->first_name->toBe('Surya')
        ->middle_name->toBe('Prakash')
        ->last_name->toBe('Sen Gupta');
});

it('reads the score from the score-only endpoint when configured to', function () {
    liveCrifScorePage(Http::response(['success' => true, 'data' => ['client_id' => 'credit_report_crif_score_TEST', 'credit_score' => '781']]));
    config(['services.surepass.crif_public_score_source' => 'score']);

    checkScoreOnPage()->assertSet('resultKind', 'live')->assertSet('resultScore', 781);

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/credit-report-crif/score'));
    Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/credit-report-crif/fetch-report'));
});

it('names a missing Surepass plan as the reason, not a bad token', function () {
    liveCrifScorePage(Http::response(['success' => false, 'message_code' => 'invalid_token', 'message' => 'Your access token is not valid for this API/Scope. Please contact support for help.'], 401));

    checkScoreOnPage()->assertSet('resultKind', 'failed');

    expect(CreditScoreCheck::query()->sole()->liveFailure())->toBe(CrifErrorCode::ApiNotEnabled);
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
    liveCrifScorePage(Http::response(crifReportWithScore('-1')));

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

it('records why a live check failed where admins can see it, never in the visitor message', function () {
    liveCrifScorePage(Http::response(['success' => false, 'message_code' => 'unauthorized'], 401));
    Log::spy();

    checkScoreOnPage()
        ->assertSet('resultKind', 'failed')
        ->assertSee('We could not fetch your CRIF score right now')
        ->assertDontSee('API token');

    $check = CreditScoreCheck::query()->sole();
    expect($check->liveFailure())->toBe(CrifErrorCode::AuthenticationFailed);

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $message === 'Public CRIF score check failed'
        && $context['error'] === 'authentication_failed'
        && $context['http_status'] === 401
        && ! str_contains(json_encode($context), 'AAAPZ9999Z')
        && ! str_contains(json_encode($context), '9000000001'))->once();

    $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->get(CreditScoreCheckResource::getUrl('view', ['record' => $check]))
        ->assertOk()
        ->assertSee('Surepass result')
        ->assertSee('Surepass rejected the API token')
        ->assertSee('PRODUCTION token')
        ->assertSee('401');
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

it('asks for a first and last name before paying for a check', function (array $name, string $missing) {
    liveCrifScorePage();

    checkScoreOnPage(...$name)
        ->assertSet('step', 'details')
        ->assertHasErrors($missing);

    expect(surepassScoreCalls())->toBe(0)
        ->and(CreditScoreCheck::query()->count())->toBe(0);
})->with([
    'no first name' => [['firstName' => ''], 'firstName'],
    'no last name' => [['lastName' => '  '], 'lastName'],
]);

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
