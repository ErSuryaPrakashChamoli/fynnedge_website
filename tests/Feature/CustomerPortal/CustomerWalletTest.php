<?php

use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Models\User;
use App\Modules\CreditBureau\Services\CrifRoles;
use App\Modules\Customers\Models\Customer;
use App\Modules\Wallet\Actions\CompleteWalletTopUp;
use App\Modules\Wallet\Contracts\PaymentGateway;
use App\Modules\Wallet\DataTransferObjects\Price;
use App\Modules\Wallet\Enums\TopUpStatus;
use App\Modules\Wallet\Enums\WalletTransactionType;
use App\Modules\Wallet\Exceptions\InsufficientWalletBalance;
use App\Modules\Wallet\Models\CustomerPlan;
use App\Modules\Wallet\Models\WalletTopUp;
use App\Modules\Wallet\Models\WalletTransaction;
use App\Modules\Wallet\Services\Wallet;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    config(['customer_portal.billing.enabled' => true]);
    $this->customer = Customer::factory()->portalAccount('9000000001')->create();
    $this->wallet = app(Wallet::class);
});

/** A stand-in gateway for tests only: real collection needs a gateway chosen and configured. */
function fakeTopUpGateway(): void
{
    app()->bind(PaymentGateway::class, fn () => new class implements PaymentGateway
    {
        public function name(): string
        {
            return 'test';
        }

        public function isConfigured(): bool
        {
            return true;
        }

        public function checkoutUrl(WalletTopUp $topUp): string
        {
            $topUp->update(['gateway_order_id' => 'order_'.$topUp->id]);

            return 'https://pay.example.test/checkout/'.$topUp->gateway_order_id;
        }
    });
}

it('shows the balance, the free-check date and every price with GST added', function () {
    $this->wallet->credit($this->customer, 25000, WalletTransactionType::Adjustment, 'Test credit');

    $this->actingAs($this->customer, 'customer')
        ->get(route('portal.wallet'))
        ->assertOk()
        ->assertSee(['₹250.00', 'Available now'])
        ->assertSee('your first refresh costs ₹99.00 + GST (₹116.82), and each later refresh ₹79.00 + GST (₹93.22)')
        ->assertSee(['₹150.00', '₹177.00 including GST', '₹250.00', '₹295.00 including GST'])
        ->assertSee('UPI top-ups are not available yet');
});

it('refuses a top-up while no payment gateway is configured, and records nothing', function () {
    $this->actingAs($this->customer, 'customer')
        ->post(route('portal.wallet.top-up'), ['amount' => 200])
        ->assertRedirect(route('portal.wallet'))
        ->assertSessionHasErrors('amount');

    expect(WalletTopUp::query()->count())->toBe(0)
        ->and($this->wallet->balance($this->customer))->toBe(0);
});

it('sends a top-up to the gateway and credits the wallet only once the gateway confirms it', function () {
    fakeTopUpGateway();

    $this->actingAs($this->customer, 'customer')
        ->post(route('portal.wallet.top-up'), ['amount' => 200])
        ->assertRedirect('https://pay.example.test/checkout/order_1');

    $topUp = WalletTopUp::query()->sole();
    expect($topUp->status)->toBe(TopUpStatus::Created)
        ->and($this->wallet->balance($this->customer))->toBe(0);

    app(CompleteWalletTopUp::class)->handle($topUp, 'pay_1', 20000);
    app(CompleteWalletTopUp::class)->handle($topUp, 'pay_1', 20000);

    expect($topUp->fresh()->status)->toBe(TopUpStatus::Paid)
        ->and($this->wallet->balance($this->customer))->toBe(20000)
        ->and(WalletTransaction::query()->where('type', WalletTransactionType::TopUp)->count())->toBe(1);
});

it('refuses a gateway confirmation for a different amount', function () {
    fakeTopUpGateway();
    $this->actingAs($this->customer, 'customer')->post(route('portal.wallet.top-up'), ['amount' => 200]);
    $topUp = WalletTopUp::query()->sole();

    expect(fn () => app(CompleteWalletTopUp::class)->handle($topUp, 'pay_1', 100))->toThrow(InvalidArgumentException::class);

    expect($topUp->fresh()->status)->toBe(TopUpStatus::Failed)
        ->and($this->wallet->balance($this->customer))->toBe(0);
});

it('validates the top-up amount', function () {
    fakeTopUpGateway();

    $this->actingAs($this->customer, 'customer')
        ->post(route('portal.wallet.top-up'), ['amount' => 5])
        ->assertSessionHasErrors('amount');
});

it('buys a plan from the wallet, and a second plan starts when the first ends', function () {
    $this->wallet->credit($this->customer, 50000, WalletTransactionType::Adjustment, 'Test credit');
    $this->actingAs($this->customer, 'customer');

    $this->post(route('portal.wallet.plans.buy', 'half_year'))->assertRedirect(route('portal.wallet'));
    $this->post(route('portal.wallet.plans.buy', 'annual'))->assertRedirect(route('portal.wallet'));

    [$first, $second] = CustomerPlan::query()->orderBy('id')->get()->all();
    expect($this->wallet->balance($this->customer))->toBe(50000 - 17700 - 29500)
        ->and($first->ends_at->toDateString())->toBe(now()->addMonths(6)->toDateString())
        ->and($second->starts_at->equalTo($first->ends_at))->toBeTrue()
        ->and($second->ends_at->toDateString())->toBe($first->ends_at->copy()->addMonths(12)->toDateString());
});

it('refuses a plan the wallet cannot pay for', function () {
    $this->actingAs($this->customer, 'customer')
        ->post(route('portal.wallet.plans.buy', 'annual'))
        ->assertRedirect(route('portal.wallet'))
        ->assertSessionHasErrors('plan');

    expect(CustomerPlan::query()->count())->toBe(0)
        ->and(WalletTransaction::query()->count())->toBe(0);
});

it('sells no plans while billing is switched off', function () {
    config(['customer_portal.billing.enabled' => false]);

    $this->actingAs($this->customer, 'customer')->post(route('portal.wallet.plans.buy', 'annual'))->assertNotFound();
});

it('never lets the balance go below zero, holds included', function () {
    $this->wallet->credit($this->customer, 15000, WalletTransactionType::Adjustment, 'Test credit');

    $this->wallet->hold($this->customer, Price::withGst(9900), WalletTransactionType::Refresh, 'Refresh');

    expect(fn () => $this->wallet->hold($this->customer, Price::withGst(7900), WalletTransactionType::Refresh, 'Refresh'))
        ->toThrow(InsufficientWalletBalance::class);
    expect($this->wallet->balance($this->customer))->toBe(15000 - 11682);
});

it('shows a customer only their own wallet history', function () {
    $other = Customer::factory()->portalAccount('9000000002')->create();
    $this->wallet->credit($other, 77700, WalletTransactionType::Adjustment, 'Other customer credit');

    $this->actingAs($this->customer, 'customer')
        ->get(route('portal.wallet'))
        ->assertOk()
        ->assertDontSee('₹777.00')
        ->assertSee('No wallet activity yet');
});

it('lets permitted staff credit and debit a wallet, never below zero', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Livewire::test(EditCustomer::class, ['record' => $this->customer->getRouteKey()])
        ->callAction(TestAction::make('adjustWallet'), data: ['direction' => 'credit', 'amount' => 500, 'reason' => 'Paid at branch'])
        ->assertHasNoFormErrors();
    Livewire::test(EditCustomer::class, ['record' => $this->customer->getRouteKey()])
        ->callAction(TestAction::make('adjustWallet'), data: ['direction' => 'debit', 'amount' => 900, 'reason' => 'Mistake']);

    expect($this->wallet->balance($this->customer))->toBe(50000)
        ->and(WalletTransaction::query()->sole())->description->toBe('Adjustment: Paid at branch')->created_by->not->toBeNull();
});

it('keeps the wallet adjustment from staff without its permission', function () {
    CrifRoles::grant();
    $officer = tap(User::factory()->create(['is_admin' => true]))->syncRoles([CrifRoles::OFFICER_ROLE]);

    $this->actingAs($officer);

    Livewire::test(EditCustomer::class, ['record' => $this->customer->getRouteKey()])
        ->assertActionHidden(TestAction::make('adjustWallet'));
});
