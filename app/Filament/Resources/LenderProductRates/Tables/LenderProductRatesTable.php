<?php

namespace App\Filament\Resources\LenderProductRates\Tables;

use App\Enums\RateSourceType;
use App\Enums\RateStatus;
use App\Enums\RateVerificationStatus;
use App\Filament\Resources\LenderProductRates\RateWorkflowActions;
use App\Models\Lender;
use App\Models\LenderProductRate;
use App\Models\LoanProduct;
use App\Support\Rates\RateFormat;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LenderProductRatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['lenderProduct.lender', 'lenderProduct.loanProduct', 'editor']))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('lenderProduct.loanProduct.name')
                    ->label('Loan type')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('lenderProduct.lender.name')
                    ->label('Bank / NBFC')
                    ->searchable()
                    ->sortable()
                    ->description(fn (LenderProductRate $record): ?string => collect([$record->variant, $record->borrower_segment ? "Only: {$record->borrower_segment}" : null])->filter()->implode(' · ') ?: null),
                TextColumn::make('rate_min')
                    ->label('Rate')
                    ->sortable()
                    ->formatStateUsing(fn (LenderProductRate $record): string => RateFormat::range((float) $record->rate_min, $record->rate_max !== null ? (float) $record->rate_max : null, $record->rate_unit))
                    ->description(fn (LenderProductRate $record): ?string => $record->promo_rate !== null
                        ? 'Offer '.RateFormat::percent((float) $record->promo_rate).($record->promo_ends_at ? ' until '.$record->promo_ends_at->timezone(LenderProductRate::TIMEZONE)->format('j M Y') : '')
                        : null),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (LenderProductRate $record): string => $record->displayStatus())
                    ->color(fn (LenderProductRate $record): string => $record->displayStatusColor()),
                TextColumn::make('verification_status')
                    ->label('Evidence')
                    ->badge()
                    ->formatStateUsing(fn (LenderProductRate $record): string => match (true) {
                        $record->verification_status !== RateVerificationStatus::Verified => $record->verification_status->getLabel(),
                        $record->source_type === RateSourceType::ThirdParty => 'Third-party',
                        $record->needsReverification() => 'Re-verify',
                        default => $record->verification_status->getLabel(),
                    })
                    ->tooltip(fn (LenderProductRate $record): ?string => $record->source_type === RateSourceType::ThirdParty
                        ? 'Read on '.($record->source_name ?: 'a comparison site').', not the lender\'s own site — confirm it with the lender.'
                        : null)
                    ->color(fn (LenderProductRate $record): string => match (true) {
                        $record->verification_status === RateVerificationStatus::Verified && $record->source_type === RateSourceType::ThirdParty => 'warning',
                        $record->needsReverification() => 'danger',
                        default => 'success',
                    }),
                TextColumn::make('last_verified_at')
                    ->label('Checked')
                    ->date('j M Y')
                    ->sortable()
                    ->placeholder('Never'),
                TextColumn::make('effective_until')
                    ->label('Valid until')
                    ->date('j M Y')
                    ->sortable()
                    ->placeholder('—'),
                IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean(),
                TextColumn::make('version')
                    ->prefix('v')
                    ->alignCenter(),
                TextColumn::make('editor.name')
                    ->label('Last edited by')
                    ->placeholder('System')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime('j M Y, g:i a', LenderProductRate::TIMEZONE)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('loan_product')
                    ->label('Loan type')
                    ->options(fn () => LoanProduct::query()->orderedForDisplay()->pluck('name', 'id'))
                    ->searchable()
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, $loanProductId) => $query->whereHas('lenderProduct', fn (Builder $offer) => $offer->where('loan_product_id', $loanProductId)),
                    )),
                SelectFilter::make('lender')
                    ->label('Bank / NBFC')
                    ->options(fn () => Lender::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, $lenderId) => $query->whereHas('lenderProduct', fn (Builder $offer) => $offer->where('lender_id', $lenderId)),
                    )),
                SelectFilter::make('status')
                    ->options(RateStatus::class),
                SelectFilter::make('verification_status')
                    ->label('Evidence')
                    ->options(RateVerificationStatus::class),
                Filter::make('needs_verification')
                    ->label('Needs verification')
                    ->query(fn (Builder $query) => $query->needsVerification()),
                Filter::make('expiring_soon')
                    ->label('Expiring within '.LenderProductRate::EXPIRING_SOON_DAYS.' days')
                    ->query(fn (Builder $query) => $query->expiringSoon()),
                Filter::make('expired')
                    ->label('Expired')
                    ->query(fn (Builder $query) => $query->expired()),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make()
                        ->visible(fn (LenderProductRate $record): bool => $record->status->isEditable()),
                    ...RateWorkflowActions::all(),
                    DeleteAction::make()
                        ->visible(fn (LenderProductRate $record): bool => $record->status === RateStatus::Draft),
                ]),
            ])
            ->toolbarActions([
                RateWorkflowActions::bulkPublish(),
            ]);
    }
}
