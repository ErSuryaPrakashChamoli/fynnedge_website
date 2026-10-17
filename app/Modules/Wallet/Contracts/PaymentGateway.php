<?php

namespace App\Modules\Wallet\Contracts;

use App\Modules\Wallet\Models\WalletTopUp;

/**
 * The seam for collecting UPI top-ups. A gateway implementation creates the
 * order with the provider (setting gateway_order_id), returns the checkout
 * URL, and — only after verifying the provider's signature or webhook
 * server-side — calls CompleteWalletTopUp. Nothing in the browser can mark a
 * top-up paid.
 */
interface PaymentGateway
{
    public function name(): string;

    public function isConfigured(): bool;

    /** Starts the provider's UPI checkout for this top-up and returns where to send the customer. */
    public function checkoutUrl(WalletTopUp $topUp): string;
}
