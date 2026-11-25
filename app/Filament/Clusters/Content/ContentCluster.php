<?php

namespace App\Filament\Clusters\Content;

use App\Filament\Clusters\AdminModuleCluster;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Articles, authors, FAQs, testimonials and the media library.
 *
 * Members are listed in App\Filament\Clusters\AdminModules.
 */
class ContentCluster extends AdminModuleCluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static ?string $navigationLabel = 'Blog & Content';

    protected static ?string $title = 'Blog & Content';

    protected static string|UnitEnum|null $navigationGroup = 'Website & Marketing';

    protected static ?int $navigationSort = 50;

    protected static ?string $slug = 'content';
}
