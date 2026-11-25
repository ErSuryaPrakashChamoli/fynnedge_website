<?php

namespace App\Filament\Clusters\Administration;

use App\Filament\Clusters\AdminModuleCluster;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Admin users, roles and permissions, API clients and the admin activity log.
 *
 * Members are listed in App\Filament\Clusters\AdminModules.
 */
class AdministrationCluster extends AdminModuleCluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Administration';

    protected static ?string $title = 'Administration';

    protected static string|UnitEnum|null $navigationGroup = 'Settings & Support';

    protected static ?int $navigationSort = 90;

    protected static ?string $slug = 'administration';
}
