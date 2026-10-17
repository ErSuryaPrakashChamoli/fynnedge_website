<?php

use App\Models\Setting;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use App\Modules\Customers\Models\Customer;
use App\Modules\Experian\Services\ExperianSettings;
use App\Modules\Wallet\Enums\CheckBilling;
use App\Modules\Wallet\Enums\WalletTransactionStatus;
use App\Modules\Wallet\Enums\WalletTransactionType;
use App\Modules\Wallet\Models\WalletTransaction;
use App\Modules\Wallet\Services\Wallet;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * Paid refreshes on the live Experian page (Surepass and SMS faked), with
 * billing switched on: first check free, then ₹99 / ₹79 + 18% GST from the
 * wallet, free again after 6 months, plans included.
 */
beforeEach(function () {
    fakeAreachSms();
    fakeSurepassExperian();
    Setting::set(ExperianSettings::PUBLIC_SCORE_ENABLED, true);
    config(['customer_portal.billing.enabled' => true]);
});

function billedCheck(string $mobile = '9000000001', string $pan = 'AAAPZ9999Z'): Testable
{
    Auth::guard('customer')->logout();

    return Livewire::test('credit-score-check', ['bureau' => 'experian'])
        ->set('mobileNumber', $mobile)->call('sendOtp')
        ->set('otpCode', lastTextedOtp())->call('verifyOtp')
        ->set('fullName', 'Test Fixture')->set('dateOfBirth', '1990-01-01')->set('panNumber', $pan)->set('consent', true)
        ->call('submitDetails');
}

function experianPulls(): int
{
    return collect(Http::recorded())->filter(fn (array $pair): bool => str_contains($pair[0]->url(), '/credit-report-experian/'))->count();
}

function walletOf(string $mobile = '9000000001'): int
{
    return app(Wallet::class)->balance(Customer::query()->where('verified_mobile', $mobile)->sole());
}

function topUpWallet(int $rupees, string $mobile = '9000000001'): void
{
    app(Wallet::class)->credit(Customer::query()->where('verified_mobile', $mobile)->sole(), $rupees * 100, WalletTransactionType::Adjustment, 'Test credit');
}

it('gives the first check free and says when the next free one is due', function () {
    billedCheck()
        ->assertSet('resultKind', 'live')
        ->assertSee('This was your free check. Your next free check is due on '.now()->addMonths(6)->translatedFormat('d M Y'));

    expect(CreditScoreCheck::query()->sole()->billing)->toBe(CheckBilling::Free)
        ->and(WalletTransaction::query()->count())->toBe(0);
});

it('stops a refresh the wallet cannot pay for, before the bureau is asked or the code is spent', function () {
    billedCheck();
    $this->travel(31)->days();

    $page = billedCheck()
        ->assertSet('step', 'details')
        ->assertSet('resultKind', null)
        ->assertSee('Add money to your wallet to refresh your score')
        ->assertSee('This refresh costs ₹99.00 + GST ₹17.82 = ₹116.82. Your wallet balance is ₹0.00.');

    expect(experianPulls())->toBe(1)
        ->and(CreditScoreCheck::query()->count())->toBe(1);

    // Topped up in another tab: the same verified code still works.
    topUpWallet(200);
    $page->call('submitDetails')
        ->assertSet('resultKind', 'live')
        ->assertSee('₹116.82 (including GST) was paid from your wallet.');

    $charge = WalletTransaction::query()->where('type', WalletTransactionType::Refresh)->sole();
    expect($charge)
        ->status->toBe(WalletTransactionStatus::Completed)
        ->amount_paise->toBe(-11682)
        ->taxable_paise->toBe(9900)
        ->gst_paise->toBe(1782)
        ->and(CreditScoreCheck::query()->latest('id')->first())->billing->toBe(CheckBilling::Wallet)->wallet_transaction_id->toBe($charge->id)
        ->and(walletOf())->toBe(20000 - 11682)
        ->and(experianPulls())->toBe(2);
});

it('charges ₹79 + GST for every refresh after the first paid one', function () {
    billedCheck();
    topUpWallet(500);
    $this->travel(31)->days();
    billedCheck();
    $this->travel(31)->days();

    billedCheck()->assertSee('₹93.22 (including GST) was paid from your wallet.');

    expect(walletOf())->toBe(50000 - 11682 - 9322);
});

it('never charges for a score that is still inside its 30-day period', function () {
    billedCheck();
    topUpWallet(500);
    $this->travel(10)->days();

    billedCheck()->assertSet('resultKind', 'live')->assertSee('No charge — your saved score is still current.');

    expect(walletOf())->toBe(50000)
        ->and(experianPulls())->toBe(1)
        ->and(CreditScoreCheck::query()->latest('id')->first()->billing)->toBe(CheckBilling::Reused);
});

it('gives the money back when the bureau returns no new result', function () {
    // A fresh fake: the first answer succeeds, the paid refresh gets a 500.
    Http::swap(new HttpFactory);
    fakeAreachSms();
    fakeSurepassExperian(Http::sequence()->push(experianFixture())->push('', 500));

    billedCheck();
    topUpWallet(500);
    $this->travel(31)->days();

    billedCheck()->assertSet('resultKind', 'pending')->assertSee('You were not charged — the bureau did not return a new result.');

    expect(walletOf())->toBe(50000)
        ->and(WalletTransaction::query()->where('type', WalletTransactionType::Refresh)->sole()->status)->toBe(WalletTransactionStatus::Released);
});

it('makes a check free again six months after the last free one', function () {
    billedCheck();
    $this->travel(6)->months();
    $this->travel(1)->days();

    billedCheck()->assertSet('resultKind', 'live')->assertSee('This was your free check.');

    expect(CreditScoreCheck::query()->where('billing', CheckBilling::Free)->count())->toBe(2);
});

it('does not give a second free check to the same PAN through another mobile number', function () {
    billedCheck('9000000001', 'AAAPZ9999Z');
    $this->travel(31)->days();

    billedCheck('9000000002', 'AAAPZ9999Z')->assertSee('Add money to your wallet to refresh your score');

    expect(experianPulls())->toBe(1);
});

it('includes refreshes in an active plan, once every 30 days per bureau', function () {
    billedCheck();
    topUpWallet(300);
    $this->actingAs(Customer::query()->sole(), 'customer')
        ->post(route('portal.wallet.plans.buy', 'half_year'))
        ->assertRedirect(route('portal.wallet'))
        ->assertSessionHas('status');
    expect(walletOf())->toBe(30000 - 17700);

    $this->travel(31)->days();
    billedCheck()->assertSet('resultKind', 'live')->assertSee('Included in your plan — no charge.');

    expect(walletOf())->toBe(30000 - 17700)
        ->and(CreditScoreCheck::query()->latest('id')->first()->billing)->toBe(CheckBilling::Plan);
});

it('leaves everything free while billing is switched off', function () {
    config(['customer_portal.billing.enabled' => false]);

    billedCheck();
    $this->travel(31)->days();
    billedCheck()->assertSet('resultKind', 'live')->assertDontSee('Add money to your wallet');

    expect(experianPulls())->toBe(2)
        ->and(WalletTransaction::query()->count())->toBe(0);
});

it('never charges for a demo score', function () {
    Setting::set(ExperianSettings::PUBLIC_SCORE_ENABLED, false);

    billedCheck();
    $this->travel(31)->days();
    billedCheck()->assertSet('resultKind', 'demo')->assertDontSee('Add money to your wallet');

    expect(WalletTransaction::query()->count())->toBe(0);
});

it('tells a returning customer the price as soon as their number is verified', function () {
    billedCheck();
    $this->travel(31)->days();
    Auth::guard('customer')->logout();

    Livewire::test('credit-score-check', ['bureau' => 'experian'])
        ->set('mobileNumber', '9000000001')->call('sendOtp')
        ->set('otpCode', lastTextedOtp())->call('verifyOtp')
        ->assertSee('Your free check for this period has been used')
        ->assertSee('this check costs ₹116.82 (₹99.00 + GST) from your wallet. Wallet balance: ₹0.00.');
});

it('shows the refresh price on My Credit Score', function () {
    billedCheck();
    $this->travel(31)->days();

    $this->actingAs(Customer::query()->sole(), 'customer')
        ->get(route('portal.credit-score'))
        ->assertOk()
        ->assertSee('Costs ₹116.82 including GST, paid from your wallet (balance ₹0.00).');
});
