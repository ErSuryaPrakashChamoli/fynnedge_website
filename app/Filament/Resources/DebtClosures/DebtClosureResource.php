<?php

namespace App\Filament\Resources\DebtClosures;

use App\Filament\Clusters\Concerns\BelongsToAdminModule;
use App\Filament\RelationManagers\AuditLogsRelationManager;
use App\Filament\Resources\DebtClosures\Pages\ListDebtClosures;
use App\Filament\Resources\DebtClosures\Pages\ViewDebtClosure;
use App\Filament\Resources\DebtClosures\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\DebtClosures\Tables\DebtClosuresTable;
use App\Modules\DebtConsolidation\Models\DebtClosure;
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
 * Debt Consolidation → Debt Closure: closure of every existing loan after
 * disbursement — quotation, payment, closure request, NOC and verification.
 */
class DebtClosureResource extends Resource
{
    use BelongsToAdminModule;

    protected static ?string $model = DebtClosure::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLockClosed;

    protected static ?string $navigationLabel = 'Debt Closure';

    protected static ?string $modelLabel = 'debt closure';

    protected static ?string $slug = 'debt-closures';

    public static function table(Table $table): Table
    {
        return DebtClosuresTable::configure($table)->defaultSort('updated_at', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        $at = fn (string $field, string $label) => TextEntry::make($field)->label($label)->dateTime('j M Y, g:i a', 'Asia/Kolkata')->placeholder('—');

        return $schema->components([
            Section::make('Closure')->columnSpanFull()->columns(4)->components([
                TextEntry::make('application.reference')->label('Case'),
                TextEntry::make('liability.lender_name')->label('Existing lender'),
                TextEntry::make('liability.account_reference_masked')->label('Loan reference')->placeholder('—'),
                TextEntry::make('status')->badge(),
                TextEntry::make('closure_type')->badge(),
                TextEntry::make('quotation.total_amount')->label('Quoted amount')->formatStateUsing(fn ($state): string => DebtPresenter::inr($state, true))->placeholder('No quotation'),
                TextEntry::make('quotation.valid_until')->label('Quotation valid to')->date('j M Y')->placeholder('—'),
                TextEntry::make('final_closure_amount')->formatStateUsing(fn ($state): string => DebtPresenter::inr($state, true))->placeholder('—'),
            ]),
            Section::make('Milestones')->columnSpanFull()->columns(4)->components([
                $at('payment_initiated_at', 'Payment initiated'),
                $at('payment_confirmed_at', 'Payment confirmed'),
                $at('closure_requested_at', 'Closure requested'),
                $at('noc_received_at', 'NOC received'),
                TextEntry::make('nocDocument.status')->label('NOC document')->badge()->placeholder('Not attached'),
                $at('verified_at', 'Closure verified'),
                TextEntry::make('verifier.name')->label('Verified by')->placeholder('—'),
                TextEntry::make('lenderApplication.lender_name')->label('Funded by')->placeholder('—'),
            ]),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['application', 'liability', 'quotation']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getRelations(): array
    {
        return [PaymentsRelationManager::class, AuditLogsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDebtClosures::route('/'),
            'view' => ViewDebtClosure::route('/{record}'),
        ];
    }
}
