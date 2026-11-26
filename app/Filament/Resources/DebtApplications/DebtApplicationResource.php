<?php

namespace App\Filament\Resources\DebtApplications;

use App\Filament\Clusters\Concerns\BelongsToAdminModule;
use App\Filament\RelationManagers\AuditLogsRelationManager;
use App\Filament\Resources\DebtApplications\Pages\CreateDebtApplication;
use App\Filament\Resources\DebtApplications\Pages\ListDebtApplications;
use App\Filament\Resources\DebtApplications\Pages\ViewDebtApplication;
use App\Filament\Resources\DebtApplications\Tables\DebtApplicationsTable;
use App\Modules\DebtConsolidation\Enums\DebtApplicationStatus;
use App\Modules\DebtConsolidation\Models\DebtApplication;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Debt Consolidation → Applications: every case, from enquiry to verified
 * closure, with the 360° case view (overview, customer, liabilities,
 * documents, assessment, suitability, savings, foreclosure, lender matching,
 * lender applications, closure, communication, timeline, audit).
 */
class DebtApplicationResource extends Resource
{
    use BelongsToAdminModule;

    protected static ?string $model = DebtApplication::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolderOpen;

    protected static ?string $navigationLabel = 'Applications';

    protected static ?string $modelLabel = 'debt consolidation case';

    protected static ?string $pluralModelLabel = 'debt consolidation cases';

    protected static ?string $slug = 'debt-applications';

    protected static ?string $recordTitleAttribute = 'reference';

    public static function table(Table $table): Table
    {
        return DebtApplicationsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['latestSnapshot', 'assignee', 'financialProfile']);
    }

    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof DebtApplication ? $record->reference.' — '.$record->displayName() : 'Debt consolidation case';
    }

    /**
     * @return list<string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['reference'];
    }

    public static function getNavigationBadge(): ?string
    {
        $new = DebtApplication::query()->where('status', DebtApplicationStatus::EnquiryReceived)->count();

        return $new > 0 ? (string) $new : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'New enquiries and submissions waiting for first contact';
    }

    /**
     * Keys are the case view's tab names (?relation=...).
     *
     * @return array<string, class-string>
     */
    public static function getRelations(): array
    {
        return [
            'liabilities' => RelationManagers\LiabilitiesRelationManager::class,
            'collateral' => RelationManagers\CollateralsRelationManager::class,
            'documents' => RelationManagers\DocumentsRelationManager::class,
            'scenarios' => RelationManagers\ScenariosRelationManager::class,
            'assessments' => RelationManagers\SnapshotsRelationManager::class,
            'matching' => RelationManagers\LenderMatchesRelationManager::class,
            'lender-applications' => RelationManagers\LenderApplicationsRelationManager::class,
            'closure' => RelationManagers\ClosuresRelationManager::class,
            'communication' => RelationManagers\NotesRelationManager::class,
            'consents' => RelationManagers\ConsentsRelationManager::class,
            'reports' => RelationManagers\ReportsRelationManager::class,
            'status-history' => RelationManagers\StatusChangesRelationManager::class,
            'audit' => AuditLogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDebtApplications::route('/'),
            'create' => CreateDebtApplication::route('/create'),
            'view' => ViewDebtApplication::route('/{record}'),
        ];
    }
}
