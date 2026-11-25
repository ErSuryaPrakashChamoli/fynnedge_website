<?php

namespace App\Filament\Clusters\CreditBureau;

use App\Filament\Clusters\AdminModuleCluster;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * CIBIL, Experian, Equifax and CRIF: dashboards, reports, requests, settings and audit history.
 *
 * Members are listed in App\Filament\Clusters\AdminModules.
 */
class CreditBureauCluster extends AdminModuleCluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Credit Bureau';

    protected static ?string $title = 'Credit Bureau';

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'credit-bureau';
}
