<?php

namespace App\Modules\Wallet\Actions;

use App\Modules\Customers\Models\Customer;
use App\Modules\Wallet\Contracts\PaymentGateway;
use App\Modules\Wallet\Enums\TopUpStatus;
use App\Modules\Wallet\Models\WalletTopUp;
use LogicException;

/**
 * Starts a UPI top-up with the configured gateway. Nothing is credited here —
 * only CompleteWalletTopUp, after the gateway confirms payment, does that.
 */
class StartWalletTopUp
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    /**
     * @return array{topUp: WalletTopUp, checkoutUrl: string}
     */
    public function handle(Customer $customer, int $amountPaise): array
    {
        if (! $this->gateway->isConfigured()) {
            throw new LogicException('Wallet top-ups are not available: no payment gateway is configured.');
        }

        $topUp = WalletTopUp::query()->create([
            'customer_id' => $customer->getKey(),
            'amount_paise' => $amountPaise,
            'gateway' => $this->gateway->name(),
            'status' => TopUpStatus::Created,
        ]);

        $customer->recordEvent('wallet_top_up_started', ['top_up' => $topUp->public_id, 'gateway' => $this->gateway->name()]);

        return ['topUp' => $topUp, 'checkoutUrl' => $this->gateway->checkoutUrl($topUp)];
    }
}
