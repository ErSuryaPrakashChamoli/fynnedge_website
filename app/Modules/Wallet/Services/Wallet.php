<?php

namespace App\Modules\Wallet\Services;

use App\Models\User;
use App\Modules\Customers\Models\Customer;
use App\Modules\Wallet\DataTransferObjects\Price;
use App\Modules\Wallet\Enums\WalletTransactionStatus;
use App\Modules\Wallet\Enums\WalletTransactionType;
use App\Modules\Wallet\Exceptions\InsufficientWalletBalance;
use App\Modules\Wallet\Models\WalletTransaction;
use App\Support\Formatting\IndianNumberFormatter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The customer wallet: a closed, rupee balance usable only for FynnOn credit
 * services (no withdrawal, no transfer). Every movement is a ledger row;
 * every write locks the customer row first, so concurrent requests are
 * serialised and the balance can never go below zero.
 */
class Wallet
{
    public function balance(Customer $customer): int
    {
        return (int) WalletTransaction::query()
            ->whereBelongsTo($customer)
            ->whereIn('status', WalletTransactionStatus::counted())
            ->sum('amount_paise');
    }

    /** 11682 → "₹116.82", 1000000 → "₹10,000.00". */
    public static function format(int $paise): string
    {
        $sign = $paise < 0 ? '-' : '';
        $paise = abs($paise);

        return $sign.'₹'.IndianNumberFormatter::format(intdiv($paise, 100)).'.'.str_pad((string) ($paise % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Reserves a charge: the amount leaves the available balance now and is
     * either captured (completed) or released later.
     *
     * @throws InsufficientWalletBalance
     */
    public function hold(Customer $customer, Price $price, WalletTransactionType $type, string $description, ?Model $reference = null): WalletTransaction
    {
        return $this->debit($customer, $price, $type, $description, WalletTransactionStatus::Held, $reference);
    }

    /**
     * Charges at once (a plan).
     *
     * @throws InsufficientWalletBalance
     */
    public function charge(Customer $customer, Price $price, WalletTransactionType $type, string $description, ?Model $reference = null): WalletTransaction
    {
        return $this->debit($customer, $price, $type, $description, WalletTransactionStatus::Completed, $reference);
    }

    /** Turns a hold into a charge. False if it was not held any more. */
    public function capture(WalletTransaction $hold, ?Model $reference = null): bool
    {
        return $this->settle($hold, WalletTransactionStatus::Completed, $reference);
    }

    /** Gives a hold back to the balance. False if it was not held any more. */
    public function release(WalletTransaction $hold): bool
    {
        return $this->settle($hold, WalletTransactionStatus::Released);
    }

    public function credit(Customer $customer, int $amountPaise, WalletTransactionType $type, string $description, ?User $by = null, ?Model $reference = null): WalletTransaction
    {
        if ($amountPaise <= 0) {
            throw new InvalidArgumentException('A credit must be a positive amount.');
        }

        return DB::transaction(function () use ($customer, $amountPaise, $type, $description, $by, $reference): WalletTransaction {
            $this->lock($customer);

            return $this->write($customer, $amountPaise, $type, WalletTransactionStatus::Completed, $description, null, $by, $reference);
        });
    }

    /**
     * A staff debit (e.g. reversing a mistaken credit). Never below zero.
     *
     * @throws InsufficientWalletBalance
     */
    public function staffDebit(Customer $customer, int $amountPaise, string $description, User $by): WalletTransaction
    {
        if ($amountPaise <= 0) {
            throw new InvalidArgumentException('A debit must be a positive amount.');
        }

        return DB::transaction(function () use ($customer, $amountPaise, $description, $by): WalletTransaction {
            $this->lock($customer);
            $this->ensureCovers($customer, $amountPaise);

            return $this->write($customer, -$amountPaise, WalletTransactionType::Adjustment, WalletTransactionStatus::Completed, $description, null, $by);
        });
    }

    /**
     * @throws InsufficientWalletBalance
     */
    private function debit(Customer $customer, Price $price, WalletTransactionType $type, string $description, WalletTransactionStatus $status, ?Model $reference): WalletTransaction
    {
        return DB::transaction(function () use ($customer, $price, $type, $description, $status, $reference): WalletTransaction {
            $this->lock($customer);
            $this->ensureCovers($customer, $price->totalPaise());

            return $this->write($customer, -$price->totalPaise(), $type, $status, $description, $price, null, $reference);
        });
    }

    private function settle(WalletTransaction $hold, WalletTransactionStatus $status, ?Model $reference = null): bool
    {
        $updated = WalletTransaction::query()
            ->whereKey($hold->getKey())
            ->where('status', WalletTransactionStatus::Held)
            ->update(array_filter([
                'status' => $status,
                'settled_at' => now(),
                'updated_at' => now(),
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
            ], fn ($value) => $value !== null));

        $hold->refresh();

        return $updated === 1;
    }

    private function lock(Customer $customer): void
    {
        Customer::query()->whereKey($customer->getKey())->lockForUpdate()->first();
    }

    /**
     * @throws InsufficientWalletBalance
     */
    private function ensureCovers(Customer $customer, int $amountPaise): void
    {
        $balance = $this->balance($customer);

        if ($balance < $amountPaise) {
            throw new InsufficientWalletBalance($balance, $amountPaise);
        }
    }

    private function write(Customer $customer, int $amountPaise, WalletTransactionType $type, WalletTransactionStatus $status, string $description, ?Price $price, ?User $by = null, ?Model $reference = null): WalletTransaction
    {
        return WalletTransaction::query()->create([
            'customer_id' => $customer->getKey(),
            'type' => $type,
            'status' => $status,
            'amount_paise' => $amountPaise,
            'taxable_paise' => $price?->taxablePaise,
            'gst_paise' => $price?->gstPaise,
            'description' => $description,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'created_by' => $by?->getKey(),
            'settled_at' => $status === WalletTransactionStatus::Held ? null : now(),
        ]);
    }
}
