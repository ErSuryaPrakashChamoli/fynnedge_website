<?php

namespace App\Modules\Wallet\Services;

use App\Modules\CreditBureau\Enums\CreditCheckStatus;
use App\Modules\CreditBureau\Services\PanNumber;
use App\Modules\CreditScore\Actions\SubmitCreditScoreCheck;
use App\Modules\CreditScore\Enums\BureauName;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use App\Modules\Customers\Models\Customer;
use App\Modules\Wallet\DataTransferObjects\BillingQuote;
use App\Modules\Wallet\DataTransferObjects\Price;
use App\Modules\Wallet\Enums\CheckBilling;
use App\Modules\Wallet\Enums\WalletTransactionStatus;
use App\Modules\Wallet\Enums\WalletTransactionType;
use App\Modules\Wallet\Exceptions\InsufficientWalletBalance;
use App\Modules\Wallet\Models\CustomerPlan;
use App\Modules\Wallet\Models\WalletTransaction;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who pays for a new credit-score check (config customer_portal.billing,
 * user decision 2026-10-17). In order:
 *
 *  1. Billing switched off, or the bureau's page is not live → no charge.
 *  2. The check would be answered from a stored score (inside the bureau's
 *     30-day reuse period) → no charge, no new pull.
 *  3. Free: the customer's first check, and again once 6 months have passed
 *     since their last free one — per customer AND per PAN, so a second
 *     mobile number never earns a second free check for the same PAN.
 *  4. An active 6-month / 1-year plan → included.
 *  5. Otherwise paid from the wallet: ₹99 for the customer's first paid
 *     refresh, ₹79 for each later one, GST added.
 *
 * A paid refresh is held before the bureau is asked and captured only when
 * the bureau gave a new answer; a failed, uncertain or reused check releases
 * it. A free check or plan refresh is used up only by a new answer too.
 */
class CreditCheckBilling
{
    public function __construct(private readonly Wallet $wallet) {}

    public static function enabled(): bool
    {
        return (bool) config('customer_portal.billing.enabled');
    }

    public function quote(Customer $customer, BureauName $bureau, ?string $pan = null, ?string $mobileNumber = null): BillingQuote
    {
        if (! self::enabled() || ! SubmitCreditScoreCheck::isLive($bureau)) {
            return new BillingQuote(null);
        }

        if ($pan !== null && $mobileNumber !== null && SubmitCreditScoreCheck::wouldAnswerFromStore($bureau, $pan, $mobileNumber)) {
            return new BillingQuote(CheckBilling::Reused);
        }

        $lastFree = $this->lastFreeCheck($customer, $pan);

        if ($lastFree === null) {
            return new BillingQuote(CheckBilling::Free);
        }

        $freeAgainAt = $lastFree->completed_at->copy()->addMonths(self::freeEveryMonths());
        $plan = $this->activePlan($customer);

        if ($plan !== null) {
            return new BillingQuote(CheckBilling::Plan, freeAgainAt: $freeAgainAt, plan: $plan);
        }

        $first = ! WalletTransaction::query()
            ->whereBelongsTo($customer)
            ->where('type', WalletTransactionType::Refresh)
            ->where('status', WalletTransactionStatus::Completed)
            ->exists();

        return new BillingQuote(
            CheckBilling::Wallet,
            Price::withGst((int) config($first ? 'customer_portal.billing.first_refresh_paise' : 'customer_portal.billing.refresh_paise')),
            $this->wallet->balance($customer),
            $freeAgainAt,
            $first,
        );
    }

    /**
     * Holds the price of a paid refresh. Null when nothing is charged.
     *
     * @throws InsufficientWalletBalance
     */
    public function reserve(Customer $customer, BureauName $bureau, BillingQuote $quote): ?WalletTransaction
    {
        if (! $quote->isCharged()) {
            return null;
        }

        return $this->wallet->hold($customer, $quote->price, WalletTransactionType::Refresh, $bureau->getLabel().' credit score refresh');
    }

    /**
     * Records how the check was paid for, and captures or releases the hold:
     * captured only for a new answer from the bureau.
     */
    public function settle(BillingQuote $quote, ?WalletTransaction $hold, CreditScoreCheck $check): void
    {
        if ($quote->mode === null) {
            return;
        }

        $created = $check->exists && $check->wasRecentlyCreated;
        $newAnswer = $created && $check->isSuccessful() && $check->reusedFromId() === null;

        if ($hold !== null) {
            $newAnswer ? $this->wallet->capture($hold, $check) : $this->wallet->release($hold);
        }

        if (! $created) {
            return;
        }

        $billing = match (true) {
            $check->reusedFromId() !== null => CheckBilling::Reused,
            ! $newAnswer || $quote->mode === CheckBilling::Reused => null,
            default => $quote->mode,
        };

        $check->forceFill([
            'billing' => $billing,
            'wallet_transaction_id' => $billing === CheckBilling::Wallet ? $hold?->getKey() : null,
        ])->save();
    }

    /** When this customer's next free check is due; null when it is available now. */
    public function nextFreeCheckAt(Customer $customer): ?CarbonInterface
    {
        return $this->lastFreeCheck($customer, null)?->completed_at->copy()->addMonths(self::freeEveryMonths());
    }

    public function activePlan(Customer $customer): ?CustomerPlan
    {
        return CustomerPlan::query()->whereBelongsTo($customer)->active()->latest('ends_at')->first();
    }

    public static function freeEveryMonths(): int
    {
        return max(1, (int) config('customer_portal.billing.free_check_every_months'));
    }

    /**
     * The newest free check — a new bureau answer billed as free, or made
     * before billing existed (billing null) — for this customer or this PAN
     * inside the free period.
     */
    private function lastFreeCheck(Customer $customer, ?string $pan): ?CreditScoreCheck
    {
        $pan = $pan !== null ? PanNumber::normalize($pan) : null;

        return CreditScoreCheck::query()
            ->genuine()
            ->where('status', CreditCheckStatus::Completed)
            ->whereNull('raw_response->reused_from')
            ->where(fn (Builder $query) => $query->whereNull('billing')->orWhere('billing', CheckBilling::Free))
            ->where('completed_at', '>=', now()->subMonths(self::freeEveryMonths()))
            ->where(fn (Builder $query) => $query->whereBelongsTo($customer)->when($pan, fn (Builder $query) => $query->orWhere('pan_number', $pan)))
            ->latest('completed_at')
            ->orderByDesc('id')
            ->first();
    }
}
