<?php

namespace App\Modules\Wallet\Actions;

use App\Modules\Wallet\Enums\TopUpStatus;
use App\Modules\Wallet\Enums\WalletTransactionType;
use App\Modules\Wallet\Models\WalletTopUp;
use App\Modules\Wallet\Models\WalletTransaction;
use App\Modules\Wallet\Services\Wallet;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Credits the wallet for a top-up the payment gateway has CONFIRMED (signature
 * or webhook verified by the gateway class). Idempotent: a webhook delivered
 * twice, or a webhook and a return redirect, credit once. A paid amount that
 * differs from the top-up is refused and the top-up marked failed.
 */
class CompleteWalletTopUp
{
    public function __construct(private readonly Wallet $wallet) {}

    public function handle(WalletTopUp $topUp, string $gatewayPaymentId, int $paidAmountPaise): WalletTransaction
    {
        // The failure is committed before the exception, so it is not rolled back with it.
        return DB::transaction(function () use ($topUp, $gatewayPaymentId, $paidAmountPaise): ?WalletTransaction {
            $topUp = WalletTopUp::query()->whereKey($topUp->getKey())->lockForUpdate()->firstOrFail();

            if ($topUp->status === TopUpStatus::Paid) {
                return $topUp->walletTransaction;
            }

            if ($paidAmountPaise !== $topUp->amount_paise) {
                $topUp->update(['status' => TopUpStatus::Failed, 'gateway_payment_id' => $gatewayPaymentId]);

                return null;
            }

            $credit = $this->wallet->credit($topUp->customer, $topUp->amount_paise, WalletTransactionType::TopUp, 'UPI top-up', reference: $topUp);

            $topUp->update([
                'status' => TopUpStatus::Paid,
                'gateway_payment_id' => $gatewayPaymentId,
                'paid_at' => now(),
                'wallet_transaction_id' => $credit->getKey(),
            ]);
            $topUp->customer->recordEvent('wallet_topped_up', ['top_up' => $topUp->public_id]);

            return $credit;
        }) ?? throw new InvalidArgumentException('The paid amount does not match the top-up.');
    }
}
