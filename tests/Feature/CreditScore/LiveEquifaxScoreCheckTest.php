<?php

use App\Filament\Pages\EquifaxDashboard;
use App\Filament\Resources\EquifaxCreditRequests\EquifaxCreditRequestResource;
use App\Filament\Resources\EquifaxCreditRequests\Pages\ListEquifaxCreditRequests;
use App\Models\Setting;
use App\Models\User;
use App\Modules\CreditBureau\Enums\ConsentMethod;
use App\Modules\CreditBureau\Enums\ConsentStatus;
use App\Modules\CreditBureau\Enums\CreditCheckStatus;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use App\Modules\Customers\Models\Customer;
use App\Modules\Equifax\Enums\EquifaxErrorCode;
use App\Modules\Equifax\Enums\EquifaxProduct;
use App\Modules\Equifax\Enums\EquifaxRequestStatus;
use App\Modules\Equifax\Enums\Gender;
use App\Modules\Equifax\Models\EquifaxCreditRequest;
use App\Modules\Equifax\Services\EquifaxSettings;
use Illuminate\Http\Client\ConnectionException;
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

    $request = EquifaxCreditRequest::query()->sole();

    expect(CreditScoreCheck::query()->sole())
        ->provider->toBe('surepass')
        ->gender->toBe('female')
        ->score->toBe(761)
        ->raw_response->toBe(['client_id' => 'credit_report_v2_TESTFIXTURE0001', 'message_code' => 'success', 'source' => 'report', 'equifax_request' => $request->public_id]);
});

it('records every paid website check as an Equifax request with its full report and the visitor\'s consent', function () {
    liveEquifaxScorePage();
    $customer = Customer::factory()->create(['phone' => '9000000001']);

    checkEquifaxOnPage(gender: 'female');

    $request = EquifaxCreditRequest::query()->sole();
    expect($request)
        ->channel->toBe('website')
        ->product->toBe(EquifaxProduct::Report)
        ->status->toBe(EquifaxRequestStatus::Success)
        ->score->toBe(761)
        ->customer_id->toBe($customer->id)
        ->requested_by->toBeNull()
        ->in_flight_key->toBeNull()
        ->pan_masked->toBe('XXXXXX999Z')
        ->gender->toBe(Gender::Female)
        ->and($request->raw_response)->toBe(equifaxReportFixture())
        ->and($request->summary)->toMatchArray(['score' => 761, 'accounts_total' => '2'])
        ->and($request->consent)->status->toBe(ConsentStatus::Given)->method->toBe(ConsentMethod::Otp)->pan_number->toBe('AAAPZ9999Z')
        ->and($request->consent->evidence_reference)->toContain(CreditScoreCheck::query()->sole()->public_id)
        ->and($request->auditLogs()->pluck('action')->all())->toContain('website_check_started');

    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $this->get('/admin/equifax-dashboard')->assertSee('Website visitors (public score page)');
    expect(collect(Livewire::test(EquifaxDashboard::class)->instance()->stats())->map(fn (array $stat) => $stat['value'])->all())
        ->toMatchArray(['Full-report requests' => 1, 'Successful' => 1]);
    Livewire::test(ListEquifaxCreditRequests::class, ['activeTab' => 'reports'])->assertCanSeeTableRecords([$request]);
    $this->get(EquifaxCreditRequestResource::getUrl('view', ['record' => $request]))
        ->assertOk()->assertSee('Website visitor (public score page)')->assertSee('FIXTURE BANK')->assertDontSee('AAAPZ9999Z');
});

it('records a failed or uncertain website check where admins look for them', function (mixed $stub, EquifaxRequestStatus $status, EquifaxErrorCode $code) {
    liveEquifaxScorePage($stub);

    checkEquifaxOnPage();

    expect(EquifaxCreditRequest::query()->sole())->channel->toBe('website')->status->toBe($status)->error_code->toBe($code)->raw_response->toBeNull()
        ->and(CreditScoreCheck::query()->sole()->raw_response['equifax_request'])->toBe(EquifaxCreditRequest::query()->sole()->public_id);
})->with([
    'no record (definite)' => [fn () => Http::response(['success' => false, 'message_code' => 'no_record_found'], 422), EquifaxRequestStatus::Failed, EquifaxErrorCode::NoRecordFound],
    'timeout (maybe charged)' => [fn () => throw new ConnectionException('cURL error 28: Operation timed out after 60001 milliseconds'), EquifaxRequestStatus::ReconciliationRequired, EquifaxErrorCode::Timeout],
]);

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

    expect(surepassEquifaxCalls())->toBe(1)
        ->and(EquifaxCreditRequest::query()->count())->toBe(1);
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
