<?php

namespace App\Filament\Resources\DebtForeclosureQuotations;

use App\Filament\Clusters\Concerns\BelongsToAdminModule;
use App\Filament\Resources\DebtApplications\DebtApplicationResource;
use App\Filament\Resources\DebtForeclosureQuotations\Pages\ListDebtForeclosureQuotations;
use App\Modules\DebtConsolidation\Enums\VerificationStatus;
use App\Modules\DebtConsolidation\Models\DebtForeclosureQuotation;
use App\Modules\DebtConsolidation\Services\DebtPresenter;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Debt Consolidation → Foreclosure Analysis: lenders' foreclosure
 * quotations across cases — what is unverified or about to expire. Record
 * new ones from a case's liabilities tab.
 */
class DebtForeclosureQuotationResource extends Resource
{
    use BelongsToAdminModule;

    protected static ?string $model = DebtForeclosureQuotation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCurrencyRupee;

    protected static ?string $navigationLabel = 'Foreclosure Analysis';

    protected static ?string $modelLabel = 'foreclosure quotation';

    protected static ?string $slug = 'debt-foreclosure-quotations';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            TextInput::make('total_amount')->numeric()->prefix('₹')->required(),
            DatePicker::make('quoted_on'),
            DatePicker::make('valid_until')->required(),
            TextInput::make('principal_outstanding')->numeric()->prefix('₹'),
            TextInput::make('accrued_interest')->numeric()->prefix('₹'),
            TextInput::make('foreclosure_charges')->numeric()->prefix('₹'),
            TextInput::make('tax_amount')->label('GST on charges')->numeric()->prefix('₹'),
            TextInput::make('overdue_amount')->numeric()->prefix('₹'),
            TextInput::make('other_charges')->numeric()->prefix('₹'),
            TextInput::make('lender_reference')->maxLength(80),
            Textarea::make('notes')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('liability.application.reference')->label('Case')->searchable()->url(fn (DebtForeclosureQuotation $record): string => DebtApplicationResource::getUrl('view', ['record' => $record->liability->application]).'?relation=foreclosure'),
                TextColumn::make('liability.lender_name')->label('Lender'),
                TextColumn::make('total_amount')->label('Quoted')->formatStateUsing(fn ($state): string => DebtPresenter::inr($state, true))->alignEnd(),
                TextColumn::make('quoted_on')->date('j M Y')->placeholder('—'),
                TextColumn::make('valid_until')->date('j M Y')->color(fn (DebtForeclosureQuotation $record): ?string => $record->isExpired() ? 'danger' : null)->sortable(),
                TextColumn::make('verification_status')->label('Verified')->badge(),
                TextColumn::make('document.label')->label('Letter')->placeholder('Not attached'),
            ])
            ->filters([
                SelectFilter::make('verification_status')->options(VerificationStatus::class),
                Filter::make('expiring')->label('Expiring in 3 days or expired')->query(fn (Builder $query): Builder => $query->whereDate('valid_until', '<=', now()->addDays(3))),
            ])
            ->recordActions([
                EditAction::make()->visible(fn (DebtForeclosureQuotation $record): bool => $record->verification_status !== VerificationStatus::Verified),
                Action::make('verify')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (DebtForeclosureQuotation $record): bool => $record->verification_status !== VerificationStatus::Verified && (bool) auth()->user()?->can('update', $record))
                    ->requiresConfirmation()
                    ->modalDescription('Confirm the amount and validity match the lender\'s foreclosure letter. Then Recalculate the case.')
                    ->action(fn (DebtForeclosureQuotation $record) => $record->update(['verification_status' => VerificationStatus::Verified, 'verified_by' => auth()->id(), 'verified_at' => now()])),
            ])
            ->defaultSort('valid_until');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['liability.application', 'document']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ListDebtForeclosureQuotations::route('/')];
    }
}
