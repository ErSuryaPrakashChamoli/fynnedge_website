<?php

namespace App\Filament\Clusters\LoanCatalog;

use App\Filament\Clusters\AdminModuleCluster;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * The loan business configuration: products, lenders, offers, rates, eligibility and application setup.
 *
 * Members are listed in App\Filament\Clusters\AdminModules.
 */
class LoanCatalogCluster extends AdminModuleCluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Loan Products & Lenders';

    protected static ?string $title = 'Loan Products & Lenders';

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'loan-catalog';
}
