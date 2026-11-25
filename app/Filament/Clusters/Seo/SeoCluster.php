<?php

namespace App\Filament\Clusters\Seo;

use App\Filament\Clusters\AdminModuleCluster;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Search engine titles, structured data, redirects, missing pages and tracking codes.
 *
 * Members are listed in App\Filament\Clusters\AdminModules.
 */
class SeoCluster extends AdminModuleCluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlassCircle;

    protected static ?string $navigationLabel = 'SEO & Performance';

    protected static ?string $title = 'SEO & Performance';

    protected static string|UnitEnum|null $navigationGroup = 'Website & Marketing';

    protected static ?int $navigationSort = 60;

    protected static ?string $slug = 'seo';
}
