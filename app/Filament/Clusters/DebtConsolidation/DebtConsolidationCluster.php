<?php

namespace App\Filament\Clusters\DebtConsolidation;

use App\Filament\Clusters\AdminModuleCluster;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Debt consolidation operations: cases from first enquiry to verified
 * closure of the old loans, with their assessments, lender matching, lender
 * applications, closures and reports.
 *
 * Members are listed in App\Filament\Clusters\AdminModules.
 */
class DebtConsolidationCluster extends AdminModuleCluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsPointingIn;

    protected static ?string $navigationLabel = 'Debt Consolidation';

    protected static ?string $title = 'Debt Consolidation';

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 15;

    protected static ?string $slug = 'debt-consolidation';
}
