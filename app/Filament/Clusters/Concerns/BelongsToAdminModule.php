<?php

namespace App\Filament\Clusters\Concerns;

use App\Filament\Clusters\AdminModules;
use UnitEnum;

/**
 * Puts a resource or page into the main module, top-bar group, position and
 * (optionally) label declared for it in {@see AdminModules}. Anything not
 * declared there falls back to the class's own properties.
 */
trait BelongsToAdminModule
{
    public static function getCluster(): ?string
    {
        return AdminModules::placementFor(static::class)['cluster'] ?? parent::getCluster();
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        $placement = AdminModules::placementFor(static::class);

        return $placement === null ? parent::getNavigationGroup() : $placement['group'];
    }

    public static function getNavigationSort(): ?int
    {
        return AdminModules::placementFor(static::class)['sort'] ?? parent::getNavigationSort();
    }

    public static function getNavigationLabel(): string
    {
        return AdminModules::placementFor(static::class)['label'] ?? parent::getNavigationLabel();
    }
}
