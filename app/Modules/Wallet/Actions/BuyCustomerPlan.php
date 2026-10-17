<?php

namespace App\Modules\Wallet\Actions;

use App\Modules\Customers\Models\Customer;
use App\Modules\Wallet\DataTransferObjects\Price;
use App\Modules\Wallet\Enums\PlanType;
use App\Modules\Wallet\Enums\WalletTransactionType;
use App\Modules\Wallet\Exceptions\InsufficientWalletBalance;
use App\Modules\Wallet\Models\CustomerPlan;
use App\Modules\Wallet\Services\CreditCheckBilling;
use App\Modules\Wallet\Services\Wallet;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Buys a refresh plan from the wallet (price + GST). A plan bought while
 * another is active starts when that one ends, so no paid time is lost.
 */
class BuyCustomerPlan
{
    public function __construct(private readonly Wallet $wallet, private readonly CreditCheckBilling $billing) {}

    /**
     * @throws InsufficientWalletBalance
     */
    public function handle(Customer $customer, PlanType $plan): CustomerPlan
    {
        $price = Price::withGst($plan->pricePaise());

        return DB::transaction(function () use ($customer, $plan, $price): CustomerPlan {
            $charge = $this->wallet->charge($customer, $price, WalletTransactionType::Plan, $plan->getLabel());

            $latestEnd = CustomerPlan::query()->whereBelongsTo($customer)->where('ends_at', '>', now())->max('ends_at');
            $startsAt = $latestEnd !== null ? now()->max(Carbon::parse($latestEnd)) : now();

            $bought = CustomerPlan::query()->create([
                'customer_id' => $customer->getKey(),
                'plan' => $plan,
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->copy()->addMonths($plan->months()),
                'price_paise' => $price->taxablePaise,
                'gst_paise' => $price->gstPaise,
                'wallet_transaction_id' => $charge->getKey(),
            ]);

            $charge->update(['reference_type' => $bought->getMorphClass(), 'reference_id' => $bought->getKey()]);
            $customer->recordEvent('plan_bought', ['plan' => $plan->value, 'plan_id' => $bought->public_id]);

            return $bought;
        });
    }
}
