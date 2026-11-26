<?php

namespace App\Modules\DebtConsolidation\Enums;

use Filament\Support\Contracts\HasLabel;

enum CollateralType: string implements HasLabel
{
    case ResidentialProperty = 'residential_property';
    case CommercialProperty = 'commercial_property';
    case IndustrialProperty = 'industrial_property';
    case Plot = 'plot';
    case Gold = 'gold';
    case Vehicle = 'vehicle';
    case Machinery = 'machinery';
    case FixedDeposit = 'fixed_deposit';
    case Securities = 'securities';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::ResidentialProperty => __('Residential property'),
            self::CommercialProperty => __('Commercial property'),
            self::IndustrialProperty => __('Industrial property'),
            self::Plot => __('Plot / land'),
            self::Gold => __('Gold'),
            self::Vehicle => __('Vehicle'),
            self::Machinery => __('Machinery / equipment'),
            self::FixedDeposit => __('Fixed deposit'),
            self::Securities => __('Shares / mutual funds'),
            self::Other => __('Other'),
        };
    }
}
