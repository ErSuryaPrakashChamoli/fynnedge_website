<?php

namespace App\Filament\Resources\DebtLenderPolicies;

use App\Filament\Clusters\Concerns\BelongsToAdminModule;
use App\Filament\RelationManagers\AuditLogsRelationManager;
use App\Filament\Resources\DebtLenderPolicies\Pages\CreateDebtLenderPolicy;
use App\Filament\Resources\DebtLenderPolicies\Pages\EditDebtLenderPolicy;
use App\Filament\Resources\DebtLenderPolicies\Pages\ListDebtLenderPolicies;
use App\Filament\Resources\DebtLenderPolicies\Schemas\DebtLenderPolicyForm;
use App\Modules\DebtConsolidation\Enums\LenderPolicyStatus;
use App\Modules\DebtConsolidation\Models\DebtLenderPolicy;
use App\Modules\DebtConsolidation\Services\MakerChecker;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Loan Products & Lenders → Debt consolidation policies: what each lender
 * offer accepts for consolidation. Extends the existing lender offers — no
 * second lender directory. A policy is used for matching only once approved
 * (Active) and inside its effective dates; changes after approval are made
 * as a new version.
 */
class DebtLenderPolicyResource extends Resource
{
    use BelongsToAdminModule;

    protected static ?string $model = DebtLenderPolicy::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Debt Consolidation Policies';

    protected static ?string $modelLabel = 'debt consolidation policy';

    protected static ?string $slug = 'debt-lender-policies';

    public static function form(Schema $schema): Schema
    {
        return DebtLenderPolicyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('lenderProduct.lender.name')->label('Lender')->searchable()->description(fn (DebtLenderPolicy $record): ?string => $record->lenderProduct?->loanProduct?->name),
                TextColumn::make('financing_route')->label('Route')->badge()->color('gray'),
                TextColumn::make('version')->prefix('v'),
                TextColumn::make('status')->badge(),
                TextColumn::make('effective_from')->date('j M Y'),
                TextColumn::make('effective_until')->date('j M Y')->placeholder('Open-ended'),
                TextColumn::make('source_name')->label('Source')->description(fn (DebtLenderPolicy $record): ?string => $record->source_received_on?->format('j M Y'))->wrap(),
                TextColumn::make('approver.name')->label('Approved by')->placeholder('—'),
            ])
            ->filters([SelectFilter::make('status')->options(LenderPolicyStatus::class)])
            ->recordActions([
                EditAction::make()->visible(fn (DebtLenderPolicy $record): bool => $record->status === LenderPolicyStatus::Draft),
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (DebtLenderPolicy $record): bool => $record->status === LenderPolicyStatus::Draft && (bool) auth()->user()?->can('Approve:DebtLenderPolicy'))
                    ->requiresConfirmation()
                    ->modalDescription('Confirm the criteria match the lender\'s source document. Approving retires the earlier active version of this offer\'s policy.')
                    ->action(function (DebtLenderPolicy $record): void {
                        if (MakerChecker::isMaker($record, auth()->id()) && ! auth()->user()?->hasRole('super_admin')) {
                            Notification::make()->title('A second person must approve a policy they did not enter or change.')->danger()->send();

                            return;
                        }

                        DebtLenderPolicy::query()->where('lender_product_id', $record->lender_product_id)->where('status', LenderPolicyStatus::Active)->update(['status' => LenderPolicyStatus::Retired]);
                        $record->update(['status' => LenderPolicyStatus::Active, 'approved_by' => auth()->id(), 'approved_at' => now()]);
                        Notification::make()->title('Policy approved and in force from '.$record->effective_from->format('j M Y'))->success()->send();
                    }),
                Action::make('retire')
                    ->icon('heroicon-o-archive-box')
                    ->color('warning')
                    ->visible(fn (DebtLenderPolicy $record): bool => $record->status === LenderPolicyStatus::Active && (bool) auth()->user()?->can('update', $record))
                    ->requiresConfirmation()
                    ->action(fn (DebtLenderPolicy $record) => $record->update(['status' => LenderPolicyStatus::Retired])),
                ReplicateAction::make()
                    ->label('New version')
                    ->visible(fn (DebtLenderPolicy $record): bool => (bool) auth()->user()?->can('create', DebtLenderPolicy::class))
                    ->excludeAttributes(['public_id', 'status', 'approved_by', 'approved_at', 'created_by'])
                    ->beforeReplicaSaved(fn (DebtLenderPolicy $replica) => $replica->fill(['status' => LenderPolicyStatus::Draft, 'created_by' => auth()->id(), 'version' => (int) DebtLenderPolicy::withTrashed()->where('lender_product_id', $replica->lender_product_id)->max('version') + 1])),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['lenderProduct.lender', 'lenderProduct.loanProduct', 'approver']);
    }

    public static function getRelations(): array
    {
        return [AuditLogsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDebtLenderPolicies::route('/'),
            'create' => CreateDebtLenderPolicy::route('/create'),
            'edit' => EditDebtLenderPolicy::route('/{record}/edit'),
        ];
    }
}
