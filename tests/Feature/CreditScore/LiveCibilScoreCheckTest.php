<?php

use App\Filament\Pages\CibilSettings as CibilSettingsPage;
use App\Filament\Resources\CreditScoreChecks\CreditScoreCheckResource;
use App\Models\Setting;
use App\Models\User;
use App\Modules\Cibil\Services\CibilRoles;
use App\Modules\Cibil\Services\CibilSettings;
use App\Modules\CreditBureau\Services\CrifRoles;
use App\Modules\CreditScore\Enums\BureauName;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use App\Modules\Experian\Enums\ExperianErrorCode;
use App\Modules\Experian\Models\ExperianCreditRequest;
use App\Modules\Experian\Services\ExperianSettings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * The public CIBIL page while FynnEdge has no CIBIL API: real scores come from
 * Experian (Surepass faked) and are always labelled as Experian's.
 */
function liveCibilScorePage(mixed $response = null): void
{
    fakeAreachSms();
    fakeSurepassExperian($response);
    Setting::set(ExperianSettings::ENABLED, false);
    Setting::set(CibilSettings::PUBLIC_SCORE_ENABLED, true);
}

function checkCibilOnPage(string $mobile = '9000000001', string $bureau = 'cibil'): Testable
{
    return Livewire::test('credit-score-check', ['bureau' => $bureau])
        ->set('mobileNumber', $mobile)
        ->call('sendOtp')
        ->set('otpCode', lastTextedOtp())
        ->call('verifyOtp')
        ->set('firstName', 'Test')
        ->set('lastName', 'Fixture')
        ->set('dateOfBirth', '1990-01-01')
        ->set('panNumber', 'aaapz9999z')
        ->set('consent', true)
        ->call('submitDetails');
}

function experianCallsFromCibil(): int
{
    return collect(Http::recorded())->filter(fn (array $pair) => str_contains($pair[0]->url(), '/credit-report-experian/'))->count();
}

it('shows the visitor their real Experian score on the CIBIL page, labelled as Experian and never as CIBIL', function () {
    liveCibilScorePage();

    checkCibilOnPage()
        ->assertSet('resultKind', 'live')
        ->assertSet('resultScore', 792)
        ->assertSee('Live score from Experian')
        ->assertSee('Your Experian score')
        ->assertSee("CIBIL isn't connected yet, so this is your score from Experian")
        ->assertDontSee('Your CIBIL score')
        ->assertDontSee('Demo score');

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/credit-report-experian/fetch-report')
        && $request->data() === ['name' => 'Test Fixture', 'pan' => 'AAAPZ9999Z', 'mobile' => '9000000001', 'consent' => 'Y']);

    $request = ExperianCreditRequest::query()->sole();

    expect(CreditScoreCheck::query()->sole())
        ->bureau->toBe(BureauName::Cibil)
        ->provider->toBe('surepass')
        ->score->toBe(792)
        ->raw_response->toBe(['client_id' => 'credit_report_experian_TESTFIXTURE0001', 'message_code' => 'success', 'source' => 'report', 'experian_request' => $request->public_id, 'source_bureau' => 'experian'])
        ->and(CreditScoreCheck::query()->sole()->sourceBureau())->toBe(BureauName::Experian)
        ->and($request)->channel->toBe('website')->score->toBe(792)
        ->and($request->consent->purpose)->toBe('Free CIBIL score check on the website');
});

it('says up front that CIBIL is not connected and which bureau will answer', function () {
    liveCibilScorePage();

    Livewire::test('credit-score-check', ['bureau' => 'cibil'])
        ->assertSee('Check your free credit score in under 2 minutes')
        ->assertSee('Get Free Credit Score')
        ->assertSee("CIBIL isn't connected yet — you'll see your real Experian score instead, free")
        ->assertDontSee('Your real CIBIL score')
        ->assertDontSee('Get Free CIBIL Score');
});

it('keeps the labelled demo when switched off, without a token, or with no known source', function (Closure $arrange) {
    liveCibilScorePage();
    $arrange();

    checkCibilOnPage()->assertSet('resultKind', 'demo')->assertSee('Demo score')->assertSee('Your CIBIL score');

    expect(experianCallsFromCibil())->toBe(0)
        ->and(CreditScoreCheck::query()->sole()->provider)->toBe('demo');
})->with([
    'switched off (the default)' => [fn () => Setting::set(CibilSettings::PUBLIC_SCORE_ENABLED, false)],
    'no Surepass token' => [fn () => config(['services.surepass.token' => null])],
    'unknown source' => [fn () => config(['services.cibil.public_score_source' => 'transunion'])],
]);

it('is switched independently of the Experian page', function () {
    liveCibilScorePage();

    checkCibilOnPage(mobile: '9000000002', bureau: 'experian')->assertSet('resultKind', 'demo');

    Setting::set(CibilSettings::PUBLIC_SCORE_ENABLED, false);
    Setting::set(ExperianSettings::PUBLIC_SCORE_ENABLED, true);
    checkCibilOnPage(mobile: '9000000003')->assertSet('resultKind', 'demo');

    expect(experianCallsFromCibil())->toBe(0);
});

it('never falls back to a demo score when Experian fails, and tells admins why', function () {
    liveCibilScorePage(Http::response(['success' => false, 'message_code' => 'invalid_token', 'message' => 'Your access token is not valid for this API/Scope.'], 401));

    checkCibilOnPage()
        ->assertSet('resultKind', 'failed')
        ->assertSet('resultScore', null)
        ->assertSee('We could not fetch your Experian score right now')
        ->assertDontSee('Demo score')
        ->assertDontSee('API/Scope');

    $check = CreditScoreCheck::query()->sole();
    expect($check->liveFailure())->toBe(ExperianErrorCode::ApiNotEnabled);

    $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->get(CreditScoreCheckResource::getUrl('index'))
        ->assertOk()
        ->assertSee('Live (Experian via Surepass)')
        ->assertSee('API not enabled on the Surepass account');
});

it('shows the same person their stored score without paying again, still labelled Experian', function () {
    liveCibilScorePage();
    checkCibilOnPage();
    $this->travel(2)->minutes();

    checkCibilOnPage()->assertSet('resultKind', 'live')->assertSet('resultScore', 792)->assertSee('Your Experian score');

    expect(experianCallsFromCibil())->toBe(1)
        ->and(CreditScoreCheck::query()->latest('id')->first()->sourceBureau())->toBe(BureauName::Experian);
});

it('lets only permitted admins switch the CIBIL page, and says where its scores come from', function () {
    CrifRoles::grant();
    CibilRoles::grant();
    config(['services.surepass.token' => 'test-surepass-token']);

    $this->actingAs(tap(User::factory()->create(['is_admin' => true]))->syncRoles([CrifRoles::OFFICER_ROLE]))
        ->get('/admin/cibil-settings')->assertForbidden();

    $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->get('/admin/cibil-settings')
        ->assertOk()
        ->assertSee('CIBIL API not connected — the page uses Experian scores')
        ->assertSee('The Experian token is configured on this server')
        ->assertDontSee('test-surepass-token');

    Livewire::test(CibilSettingsPage::class)
        ->fillForm([CibilSettings::PUBLIC_SCORE_ENABLED => true])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(CibilSettings::publicScoreEnabled())->toBeTrue()
        ->and(ExperianSettings::publicScoreEnabled())->toBeFalse();
});
