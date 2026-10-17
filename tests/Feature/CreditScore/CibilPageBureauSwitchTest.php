<?php

use App\Models\ApiClient;
use App\Models\Setting;
use App\Modules\Cibil\Models\CibilCreditRequest;
use App\Modules\Cibil\Services\CibilRoles;
use App\Modules\Cibil\Services\CibilSettings;
use App\Modules\CreditBureau\Enums\CreditCheckStatus;
use App\Modules\CreditBureau\Services\CrifRoles;
use App\Modules\CreditScore\Actions\SubmitCreditScoreCheck;
use App\Modules\CreditScore\Enums\BureauName;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use App\Modules\Experian\Models\ExperianCreditRequest;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * The Get CIBIL Score page's two switches (CIBIL Settings): "Real CIBIL score
 * enabled" and "Fetch Experian instead of CIBIL". Both bureaus' Surepass
 * endpoints are faked and configured, so only the switches decide who is
 * called; stray requests are refused, so nothing real is ever paid for.
 */
function fakeCibilPageBureaus(mixed $cibil = null, mixed $experian = null): void
{
    fakeAreachSms();
    fakeSurepassCibil($cibil);
    fakeSurepassExperian($experian);
}

function setCibilPageSwitches(bool $realScores, bool $useExperian): void
{
    Setting::set(CibilSettings::PUBLIC_SCORE_ENABLED, $realScores);
    Setting::set(CibilSettings::PUBLIC_SCORE_USE_EXPERIAN, $useExperian);
}

function checkScoreOnCibilPage(): Testable
{
    return Livewire::test('credit-score-check', ['bureau' => 'cibil'])
        ->set('mobileNumber', '9000000001')
        ->call('sendOtp')
        ->set('otpCode', lastTextedOtp())
        ->call('verifyOtp')
        ->set('fullName', 'Test Fixture')
        ->set('dateOfBirth', '1990-01-01')
        ->set('panNumber', 'aaapz9999z')
        ->set('gender', 'male')
        ->set('consent', true)
        ->call('submitDetails');
}

function surepassCallsTo(string $bureau): int
{
    return collect(Http::recorded())->filter(fn (array $pair) => str_contains($pair[0]->url(), "/credit-report-{$bureau}/"))->count();
}

/**
 * @return array{full_name: string, first_name: null, middle_name: null, last_name: null, date_of_birth: string, pan_number: string, gender: string}
 */
function cibilPageDetails(): array
{
    return ['full_name' => 'Test Fixture', 'first_name' => null, 'middle_name' => null, 'last_name' => null, 'date_of_birth' => '1990-01-01', 'pan_number' => 'AAAPZ9999Z', 'gender' => 'male'];
}

it('shows a labelled demo score and calls no bureau while real scores are off, whichever bureau is chosen', function (bool $useExperian) {
    fakeCibilPageBureaus();
    setCibilPageSwitches(realScores: false, useExperian: $useExperian);

    checkScoreOnCibilPage()
        ->assertSet('resultKind', 'demo')
        ->assertSee('Demo score — not your real credit report');

    expect(surepassCallsTo('cibil'))->toBe(0)
        ->and(surepassCallsTo('experian'))->toBe(0)
        ->and(CreditScoreCheck::query()->sole()->provider)->toBe('demo');
})->with([
    'A: Experian off' => [false],
    'B: Experian on' => [true],
]);

it('C: fetches the real CIBIL score from the CIBIL endpoint only', function () {
    fakeCibilPageBureaus();
    setCibilPageSwitches(realScores: true, useExperian: false);

    checkScoreOnCibilPage()
        ->assertSet('resultKind', 'live')
        ->assertSet('resultScore', 750)
        ->assertSee('Live score from CIBIL')
        ->assertSee('Your CIBIL score');

    expect(surepassCallsTo('cibil'))->toBe(1)
        ->and(surepassCallsTo('experian'))->toBe(0)
        ->and(CreditScoreCheck::query()->sole()->sourceBureau())->toBe(BureauName::Cibil)
        ->and(CibilCreditRequest::query()->count())->toBe(1)
        ->and(ExperianCreditRequest::query()->count())->toBe(0);
});

it('D: fetches the real Experian score from the Experian endpoint only, shown and stored as Experian\'s', function () {
    fakeCibilPageBureaus();
    setCibilPageSwitches(realScores: true, useExperian: true);

    checkScoreOnCibilPage()
        ->assertSet('resultKind', 'live')
        ->assertSet('resultScore', 792)
        ->assertSee('Live score from Experian')
        ->assertSee('Your Experian score')
        ->assertDontSee('Your CIBIL score');

    expect(surepassCallsTo('experian'))->toBe(1)
        ->and(surepassCallsTo('cibil'))->toBe(0)
        ->and(CreditScoreCheck::query()->sole()->sourceBureau())->toBe(BureauName::Experian)
        ->and(CibilCreditRequest::query()->count())->toBe(0)
        ->and(ExperianCreditRequest::query()->sole()->consent->purpose)->toBe('Free Experian score check on the website (CIBIL page)');
});

it('E: never reuses a stored CIBIL score as an Experian one, or the other way round', function () {
    fakeCibilPageBureaus();
    setCibilPageSwitches(realScores: true, useExperian: false);
    $cibil = app(SubmitCreditScoreCheck::class)->handle(cibilPageDetails(), BureauName::Cibil, '9000000001');

    setCibilPageSwitches(realScores: true, useExperian: true);
    $experian = app(SubmitCreditScoreCheck::class)->handle(cibilPageDetails(), BureauName::Cibil, '9000000001');

    expect($experian)->score->toBe(792)
        ->and($experian->reusedFromId())->toBeNull()
        ->and($experian->sourceBureau())->toBe(BureauName::Experian);

    setCibilPageSwitches(realScores: true, useExperian: false);
    $cibilAgain = app(SubmitCreditScoreCheck::class)->handle(cibilPageDetails(), BureauName::Cibil, '9000000001');

    setCibilPageSwitches(realScores: true, useExperian: true);
    $experianAgain = app(SubmitCreditScoreCheck::class)->handle(cibilPageDetails(), BureauName::Cibil, '9000000001');

    expect($cibilAgain)->score->toBe(750)
        ->and($cibilAgain->reusedFromId())->toBe($cibil->public_id)
        ->and($cibilAgain->sourceBureau())->toBe(BureauName::Cibil)
        ->and($experianAgain->reusedFromId())->toBe($experian->public_id)
        ->and($experianAgain->sourceBureau())->toBe(BureauName::Experian)
        ->and(surepassCallsTo('cibil'))->toBe(1)
        ->and(surepassCallsTo('experian'))->toBe(1);
});

it('E: holds an uncertain check only against the bureau it was sent to', function () {
    fakeCibilPageBureaus();
    CreditScoreCheck::factory()->create([
        'bureau' => BureauName::Cibil,
        'provider' => CreditScoreCheck::LIVE_PROVIDER,
        'status' => CreditCheckStatus::Pending,
        'pan_number' => 'AAAPZ9999Z',
        'mobile_number' => '9000000001',
        'raw_response' => ['source_bureau' => 'experian'],
        'score' => null,
        'completed_at' => null,
    ]);

    setCibilPageSwitches(realScores: true, useExperian: true);
    expect(SubmitCreditScoreCheck::wouldAnswerFromStore(BureauName::Cibil, 'AAAPZ9999Z', '9000000001'))->toBeTrue();

    setCibilPageSwitches(realScores: true, useExperian: false);
    expect(SubmitCreditScoreCheck::wouldAnswerFromStore(BureauName::Cibil, 'AAAPZ9999Z', '9000000001'))->toBeFalse();
});

it('E: shows the saved CIBIL score again but never as the answer to an Experian check on the same page', function () {
    fakeCibilPageBureaus();
    setCibilPageSwitches(realScores: true, useExperian: false);
    checkScoreOnCibilPage()->assertSet('resultKind', 'live');
    $this->travel(2)->minutes();

    setCibilPageSwitches(realScores: true, useExperian: true);
    checkScoreOnCibilPage()
        ->assertSet('step', 'details')
        ->assertSet('resultScore', null)
        ->assertNotSet('limitReached', null)
        ->assertDontSee('Your Experian score');
    $this->travel(2)->minutes();

    setCibilPageSwitches(realScores: true, useExperian: false);
    checkScoreOnCibilPage()->assertSet('resultKind', 'live')->assertSet('resultScore', 750)->assertSee('Your CIBIL score');

    expect(surepassCallsTo('cibil'))->toBe(1)
        ->and(surepassCallsTo('experian'))->toBe(0);
});

it('F: reports the chosen bureau\'s failure and never asks the other bureau instead', function (bool $useExperian, Closure $fake, string $failedBureau, string $otherBureau) {
    $fake();
    setCibilPageSwitches(realScores: true, useExperian: $useExperian);

    checkScoreOnCibilPage()
        ->assertSet('resultKind', 'failed')
        ->assertSet('resultScore', null)
        ->assertDontSee('Demo score');

    expect(surepassCallsTo($failedBureau))->toBe(1)
        ->and(surepassCallsTo($otherBureau))->toBe(0);
})->with([
    'CIBIL finds no record' => [false, fn () => fakeCibilPageBureaus(cibil: Http::response(['success' => false, 'message_code' => 'no_record_found'], 422)), 'cibil', 'experian'],
    'Experian rejects the token' => [true, fn () => fakeCibilPageBureaus(experian: Http::response(['success' => false, 'message_code' => 'invalid_token', 'message' => 'Your access token is not valid for this API/Scope.'], 401)), 'experian', 'cibil'],
]);

it('asks the visitor to submit again when the bureau changes after the details step, before anything is spent', function () {
    fakeCibilPageBureaus();
    setCibilPageSwitches(realScores: true, useExperian: false);

    $page = Livewire::test('credit-score-check', ['bureau' => 'cibil'])
        ->set('mobileNumber', '9000000001')->call('sendOtp')->set('otpCode', lastTextedOtp())->call('verifyOtp')
        ->set('fullName', 'Test Fixture')->set('dateOfBirth', '1990-01-01')->set('panNumber', 'AAAPZ9999Z')->set('gender', 'male')->set('consent', true);

    setCibilPageSwitches(realScores: true, useExperian: true);
    $page->call('submitDetails')
        ->assertSet('step', 'details')
        ->assertHasErrors(['consent' => 'Where your score comes from has just changed — please check the details above and submit again.']);

    expect(CreditScoreCheck::query()->count())->toBe(0);

    $page->call('submitDetails')->assertSet('resultKind', 'live')->assertSet('resultScore', 792);
    expect(surepassCallsTo('cibil'))->toBe(0)->and(surepassCallsTo('experian'))->toBe(1);
});

it('keeps a shown result labelled with the bureau that answered after the switch flips', function () {
    fakeCibilPageBureaus();
    setCibilPageSwitches(realScores: true, useExperian: true);
    $page = checkScoreOnCibilPage()->assertSee('Your Experian score');

    setCibilPageSwitches(realScores: true, useExperian: false);

    $page->call('$refresh')
        ->assertSee('Your Experian score')
        ->assertSee('Live score from Experian')
        ->assertDontSee('Your CIBIL score');
});

it('G: leaves the LMS on CIBIL whatever the page switches say', function () {
    fakeCibilPageBureaus();
    CrifRoles::grant();
    CibilRoles::grant();
    setCibilPageSwitches(realScores: true, useExperian: true);
    $client = ApiClient::factory()->create();
    $client->user->syncRoles([CrifRoles::OFFICER_ROLE]);

    $this->postJson('/api/v1/external/cibil/reports', [
        'name' => 'Test Fixture',
        'mobile' => '9000000001',
        'pan' => 'AAAPZ9999Z',
        'gender' => 'male',
        'consent' => ['method' => 'recorded_call', 'evidence_reference' => 'LMS-CALL-001', 'consented_at' => now()->subHour()->toIso8601String(), 'attested' => true],
    ], ['Authorization' => 'Bearer '.$client->issueKey()])->assertStatus(202);

    expect(CibilCreditRequest::query()->sole()->channel)->toBe('external')
        ->and(surepassCallsTo('cibil'))->toBe(1)
        ->and(surepassCallsTo('experian'))->toBe(0)
        ->and(CibilSettings::enabled())->toBeTrue();
});
