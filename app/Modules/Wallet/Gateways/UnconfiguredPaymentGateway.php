<?php

namespace App\Modules\Wallet\Gateways;

use App\Modules\Wallet\Contracts\PaymentGateway;
use App\Modules\Wallet\Models\WalletTopUp;
use LogicException;

/**
 * Bound until a real gateway (Razorpay, Cashfree, PhonePe…) is chosen and its
 * keys are set: top-ups are switched off and nothing pretends to be paid.
 */
class UnconfiguredPaymentGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'none';
    }

    public function isConfigured(): bool
    {
        return false;
    }

    public function checkoutUrl(WalletTopUp $topUp): string
    {
        throw new LogicException('No payment gateway is configured for wallet top-ups.');
    }
}
