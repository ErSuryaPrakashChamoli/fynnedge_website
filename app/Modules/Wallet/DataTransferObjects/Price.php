<?php

namespace App\Modules\Wallet\DataTransferObjects;

/**
 * A price in paise, GST added on top (config customer_portal.billing.gst_percent).
 */
final readonly class Price
{
    public function __construct(
        public int $taxablePaise,
        public int $gstPaise,
    ) {}

    public static function withGst(int $taxablePaise): self
    {
        return new self($taxablePaise, (int) round($taxablePaise * (float) config('customer_portal.billing.gst_percent') / 100));
    }

    public function totalPaise(): int
    {
        return $this->taxablePaise + $this->gstPaise;
    }
}
