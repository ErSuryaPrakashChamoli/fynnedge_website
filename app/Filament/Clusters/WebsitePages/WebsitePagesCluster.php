<?php

namespace App\Filament\Clusters\WebsitePages;

use App\Filament\Clusters\AdminModuleCluster;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Every public page type, the guided Create a Page flow, and the homepage sections around them.
 *
 * Members are listed in App\Filament\Clusters\AdminModules.
 */
class WebsitePagesCluster extends AdminModuleCluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?string $navigationLabel = 'Website Pages';

    protected static ?string $title = 'Website Pages';

    protected static string|UnitEnum|null $navigationGroup = 'Website & Marketing';

    protected static ?int $navigationSort = 40;

    protected static ?string $slug = 'website';
}
