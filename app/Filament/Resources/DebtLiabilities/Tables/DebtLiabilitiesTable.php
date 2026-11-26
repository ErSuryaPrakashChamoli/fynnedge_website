<?php

namespace App\Filament\Resources\DebtLiabilities\Tables;

use App\Filament\Resources\DebtApplications\DebtApplicationResource;
use App\Modules\DebtConsolidation\Enums\LiabilityType;
use App\Modules\DebtConsolidation\Enums\VerificationStatus;
use App\Modules\DebtConsolidation\Models\DebtForeclosureQuotation;
use App\Modules\DebtConsolidation\Models\DebtLiability;
use App\Modules\DebtConsolidation\Services\DebtPresenter;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DebtLiabilitiesTable
{
    public static function configure(Table $table, bool $withCase = true): Table
    {
        return $table
            ->columns(array_values(array_filter([
                $withCase ? TextColumn::make('application.reference')->label('Case')->searchable()->url(fn (DebtLiability $record): string => DebtApplicationResource::getUrl('view', ['record' => $record->application]).'?relation=liabilities') : null,
                TextColumn::make('liability_type')->label('Type')->badge()->color('gray'),
                TextColumn::make('lender_name')->label('Lender')->searchable()->description(fn (DebtLiability $record): ?string => $record->account_reference_masked),
                TextColumn::make('outstanding_principal')->label('Outstanding')->formatStateUsing(fn ($state): string => DebtPresenter::inr($state, true))->alignEnd()->sortable(),
                TextColumn::make('interest_rate')->label('Rate')->suffix('%')->alignEnd(),
                TextColumn::make('monthly_payment')->label('EMI / payment')->formatStateUsing(fn ($state): string => DebtPresenter::inr($state, true))->placeholder('—')->alignEnd(),
                TextColumn::make('remaining_months')->label('Months')->placeholder('—')->alignEnd(),
                TextColumn::make('consolidation_mode')->label('In scope')->badge(),
                TextColumn::make('account_status')->label('Standing')->badge()->color(fn (DebtLiability $record): string => $record->account_status?->blocksConsolidation() ? 'danger' : 'gray'),
                TextColumn::make('verification_status')->label('Verified')->badge(),
            ])))
            ->filters([
                SelectFilter::make('liability_type')->label('Type')->options(LiabilityType::class),
                SelectFilter::make('verification_status')->options(VerificationStatus::class),
            ])
            ->recordActions([
                EditAction::make()->modalWidth('5xl'),
                Action::make('verify')
                    ->label('Verify')
                    ->icon('heroicon-o-check-badge')
                    ->visible(fn (DebtLiability $record): bool => (bool) auth()->user()?->can('update', $record))
                    ->schema([
                        Select::make('verification_status')->options(VerificationStatus::class)->required(),
                        Textarea::make('note')->label('Checked against')->required()->maxLength(500)->helperText('e.g. "Loan statement dated 20 Nov 2026 (document v1)".'),
                    ])
                    ->action(function (DebtLiability $record, array $data): void {
                        $record->update(['verification_status' => $data['verification_status'], 'verified_by' => auth()->id(), 'verified_at' => now()]);
                        $record->application->recordEvent('liability_verified', ['liability' => $record->public_id, 'status' => $data['verification_status'] instanceof VerificationStatus ? $data['verification_status']->value : $data['verification_status'], 'note' => $data['note']], auth()->id());
                        Notification::make()->title('Verification recorded')->success()->send();
                    }),
                Action::make('quotation')
                    ->label('Record quotation')
                    ->icon('heroicon-o-document-currency-rupee')
                    ->visible(fn (): bool => (bool) auth()->user()?->can('create', DebtForeclosureQuotation::class))
                    ->schema([
                        TextInput::make('total_amount')->label('Total foreclosure amount')->numeric()->prefix('₹')->required()->minValue(1),
                        DatePicker::make('quoted_on')->required()->default(now()),
                        DatePicker::make('valid_until')->required(),
                        TextInput::make('foreclosure_charges')->numeric()->prefix('₹'),
                        TextInput::make('tax_amount')->label('GST on charges')->numeric()->prefix('₹'),
                        TextInput::make('lender_reference')->maxLength(80),
                        Select::make('debt_document_id')->label('Foreclosure letter (uploaded document)')
                            ->options(fn (DebtLiability $record) => $record->application->documents()->get()->mapWithKeys(fn ($document) => [$document->id => $document->title().' v'.$document->version]))
                            ->helperText('Upload it in the Documents tab first.'),
                    ])
                    ->action(function (DebtLiability $record, array $data): void {
                        $record->quotations()->create([...$data, 'recorded_by' => auth()->id()]);
                        Notification::make()->title('Quotation recorded — verify it, then Recalculate')->success()->send();
                    }),
            ])
            ->defaultSort('sort_order');
    }
}
