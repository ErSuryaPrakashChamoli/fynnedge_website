<?php

namespace App\Filament\Clusters\Leads;

use App\Filament\Clusters\AdminModuleCluster;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Every enquiry, OTP verification, credit score check and application that reaches the website, in one place.
 *
 * Members are listed in App\Filament\Clusters\AdminModules.
 */
class LeadsCluster extends AdminModuleCluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?string $navigationLabel = 'Leads & Enquiries';

    protected static ?string $title = 'Leads & Enquiries';

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'leads';
}
