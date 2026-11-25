<?php

namespace App\Modules\FynnOn\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Delivery state of one lead to FynnOn LMS. Each attempt's outcome is an
 * immutable `audit_logs` row on the lead (action = value below), so the latest
 * row is the current state and the rows together are the delivery history.
 * No row = not sent yet (Pending).
 *
 * Only a 2xx reply from the LMS that acknowledges the lead counts as Sent; a
 * request that was merely started never does.
 */
enum FynnOnSyncStatus: string implements HasColor, HasLabel
{
    case Pending = 'fynnon.pending';
    case Sent = 'fynnon.sent';
    case Duplicate = 'fynnon.duplicate';
    case Failed = 'fynnon.failed';
    case Abandoned = 'fynnon.abandoned';

    public const ACTION_PREFIX = 'fynnon.';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Not sent yet',
            self::Sent => 'Delivered',
            self::Duplicate => 'Already in LMS',
            self::Failed => 'Failed — retrying',
            self::Abandoned => 'Failed — needs retry',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Sent, self::Duplicate => 'success',
            self::Failed => 'warning',
            self::Abandoned => 'danger',
        };
    }

    public function isDelivered(): bool
    {
        return $this === self::Sent || $this === self::Duplicate;
    }

    /**
     * The audit_logs actions that represent an attempt outcome (Pending is the
     * absence of one and is never written).
     *
     * @return array<int, string>
     */
    public static function recordedActions(): array
    {
        return [self::Sent->value, self::Duplicate->value, self::Failed->value, self::Abandoned->value];
    }

    public static function fromAction(?string $action): self
    {
        return self::tryFrom((string) $action) ?? self::Pending;
    }
}
