<?php

namespace App\Filament\Clusters\Marketing;

use App\Filament\Clusters\AdminModuleCluster;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * The newsletter: subscribers, campaigns, templates, segments and sender settings.
 *
 * Members are listed in App\Filament\Clusters\AdminModules.
 */
class MarketingCluster extends AdminModuleCluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $navigationLabel = 'Email Marketing';

    protected static ?string $title = 'Email Marketing';

    protected static string|UnitEnum|null $navigationGroup = 'Website & Marketing';

    protected static ?int $navigationSort = 65;

    protected static ?string $slug = 'marketing';
}
