<?php

namespace App\Modules\CreditBureau\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The life of one paid CRIF report attempt:
 *
 *   pending ──▶ processing ──▶ success ──▶ expired (retention purge)
 *      │             ├──────▶ failed
 *      └──▶ failed   └──────▶ reconciliation_required ──▶ failed (resolved)
 *
 * Pending, processing and reconciliation_required are "in flight": while a row
 * is in one of them it holds the PAN's in_flight_key, so no second paid request
 * for that PAN can start. Reconciliation_required means the provider may have
 * processed (and billed) the call but we do not know — it is only ever left by
 * an explicit admin decision, never by an automatic retry.
 */
enum CrifReportStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Success = 'success';
    case Failed = 'failed';
    case ReconciliationRequired = 'reconciliation_required';
    case Expired = 'expired';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::Success => 'Success',
            self::Failed => 'Failed',
            self::ReconciliationRequired => 'Reconciliation required',
            self::Expired => 'Expired (data purged)',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending, self::Processing => 'info',
            self::Success => 'success',
            self::Failed => 'danger',
            self::ReconciliationRequired => 'warning',
            self::Expired => 'gray',
        };
    }

    public function isInFlight(): bool
    {
        return in_array($this, self::inFlight(), true);
    }

    /**
     * @return list<self>
     */
    public static function inFlight(): array
    {
        return [self::Pending, self::Processing, self::ReconciliationRequired];
    }
}
