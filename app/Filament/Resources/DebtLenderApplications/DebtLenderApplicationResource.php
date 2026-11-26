<?php

namespace App\Filament\Resources\DebtLenderApplications;

use App\Filament\Clusters\Concerns\BelongsToAdminModule;
use App\Filament\RelationManagers\AuditLogsRelationManager;
use App\Filament\Resources\DebtLenderApplications\Pages\ListDebtLenderApplications;
use App\Filament\Resources\DebtLenderApplications\Pages\ViewDebtLenderApplication;
use App\Filament\Resources\DebtLenderApplications\RelationManagers\StatusChangesRelationManager;
use App\Filament\Resources\DebtLenderApplications\Tables\DebtLenderApplicationsTable;
use App\Modules\DebtConsolidation\Models\DebtLenderApplication;
use App\Modules\DebtConsolidation\Services\DebtPresenter;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Debt Consolidation → Lender Applications: every application facilitated
 * to a lender, with the lender's own status recorded from the lender.
 */
class DebtLenderApplicationResource extends Resource
{
    use BelongsToAdminModule;

    protected static ?string $model = DebtLenderApplication::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static ?string $navigationLabel = 'Lender Applications';

    protected static ?string $modelLabel = 'lender application';

    protected static ?string $slug = 'debt-lender-applications';

    public static function table(Table $table): Table
    {
        return DebtLenderApplicationsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        $money = fn ($state): string => DebtPresenter::inr($state);

        return $schema->columns(3)->components([
            Section::make('Application')->columnSpanFull()->columns(4)->components([
                TextEntry::make('application.reference')->label('Case'),
                TextEntry::make('lender_name')->label('Lender'),
                TextEntry::make('product_name')->label('Product'),
                TextEntry::make('status')->badge(),
                TextEntry::make('lender_reference')->label('Lender ref.')->placeholder('—'),
                TextEntry::make('consent.granted_at')->label('Customer consent to share')->dateTime('j M Y, g:i a', 'Asia/Kolkata')->placeholder('Missing'),
                TextEntry::make('submitted_at')->dateTime('j M Y', 'Asia/Kolkata')->placeholder('—'),
                TextEntry::make('decision_at')->label('Lender decision')->dateTime('j M Y', 'Asia/Kolkata')->placeholder('—'),
            ]),
            Section::make('Amounts')->columnSpanFull()->columns(4)->components([
                TextEntry::make('applied_amount')->formatStateUsing($money)->placeholder('—'),
                TextEntry::make('sanctioned_amount')->formatStateUsing($money)->placeholder('—'),
                TextEntry::make('sanctioned_rate')->suffix('%')->placeholder('—'),
                TextEntry::make('sanctioned_tenure_months')->label('Tenure (months)')->placeholder('—'),
                TextEntry::make('processing_fee_amount')->formatStateUsing($money)->placeholder('—'),
                TextEntry::make('disbursed_amount')->formatStateUsing($money)->placeholder('—'),
                TextEntry::make('disbursed_at')->dateTime('j M Y', 'Asia/Kolkata')->placeholder('—'),
                TextEntry::make('rejection_reason')->placeholder('—'),
            ]),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('application');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getRelations(): array
    {
        return [StatusChangesRelationManager::class, AuditLogsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDebtLenderApplications::route('/'),
            'view' => ViewDebtLenderApplication::route('/{record}'),
        ];
    }
}
