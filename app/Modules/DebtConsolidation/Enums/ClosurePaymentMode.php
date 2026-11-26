<?php

namespace App\Modules\DebtConsolidation\Enums;

use Filament\Support\Contracts\HasLabel;

enum ClosurePaymentMode: string implements HasLabel
{
    case LenderDirect = 'lender_direct';
    case Neft = 'neft';
    case Rtgs = 'rtgs';
    case Imps = 'imps';
    case Upi = 'upi';
    case DemandDraft = 'demand_draft';
    case Cheque = 'cheque';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::LenderDirect => __('Paid directly by the new lender'),
            self::Neft => 'NEFT',
            self::Rtgs => 'RTGS',
            self::Imps => 'IMPS',
            self::Upi => 'UPI',
            self::DemandDraft => __('Demand draft'),
            self::Cheque => __('Cheque'),
            self::Other => __('Other'),
        };
    }
}
