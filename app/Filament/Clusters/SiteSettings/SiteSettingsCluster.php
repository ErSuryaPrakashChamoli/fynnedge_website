<?php

namespace App\Filament\Clusters\SiteSettings;

use App\Filament\Clusters\AdminModuleCluster;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Site-wide settings and the wording of fixed pages (About, Quick Enquiry, Calculators, Credit Score).
 *
 * Members are listed in App\Filament\Clusters\AdminModules.
 */
class SiteSettingsCluster extends AdminModuleCluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $navigationLabel = 'Website Settings';

    protected static ?string $title = 'Website Settings';

    protected static string|UnitEnum|null $navigationGroup = 'Settings & Support';

    protected static ?int $navigationSort = 85;

    protected static ?string $slug = 'site-settings';
}
