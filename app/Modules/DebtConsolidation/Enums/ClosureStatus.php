<?php

namespace App\Modules\DebtConsolidation\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Closure of one existing liability. A liability is closed only at
 * ClosureVerified, which needs confirmed payment and closure evidence —
 * never because the new loan was disbursed.
 */
enum ClosureStatus: string implements HasColor, HasLabel
{
    case Planned = 'planned';
    case QuotationRequested = 'quotation_requested';
    case QuotationReceived = 'quotation_received';
    case PaymentInitiated = 'payment_initiated';
    case PaymentConfirmed = 'payment_confirmed';
    case ClosureRequested = 'closure_requested';
    case NocAwaited = 'noc_awaited';
    case NocReceived = 'noc_received';
    case ClosureVerified = 'closure_verified';
    case OnHold = 'on_hold';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Planned => __('Planned'),
            self::QuotationRequested => __('Foreclosure quotation requested'),
            self::QuotationReceived => __('Foreclosure quotation received'),
            self::PaymentInitiated => __('Payment initiated'),
            self::PaymentConfirmed => __('Payment confirmed'),
            self::ClosureRequested => __('Closure requested'),
            self::NocAwaited => __('NOC awaited'),
            self::NocReceived => __('NOC received'),
            self::ClosureVerified => __('Closure verified'),
            self::OnHold => __('On hold'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::ClosureVerified => 'success',
            self::NocReceived, self::PaymentConfirmed => 'info',
            self::OnHold => 'warning',
            self::Cancelled => 'danger',
            default => 'gray',
        };
    }

    public function isFinished(): bool
    {
        return in_array($this, [self::ClosureVerified, self::Cancelled], true);
    }

    /**
     * Position in the normal sequence, for progress bars (null when off it).
     */
    public function step(): ?int
    {
        $sequence = [self::Planned, self::QuotationRequested, self::QuotationReceived, self::PaymentInitiated, self::PaymentConfirmed, self::ClosureRequested, self::NocAwaited, self::NocReceived, self::ClosureVerified];
        $index = array_search($this, $sequence, true);

        return $index === false ? null : $index + 1;
    }

    public static function stepCount(): int
    {
        return 9;
    }
}
