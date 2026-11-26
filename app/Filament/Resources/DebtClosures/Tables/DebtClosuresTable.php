<?php

namespace App\Filament\Resources\DebtClosures\Tables;

use App\Filament\Resources\DebtApplications\DebtApplicationResource;
use App\Filament\Resources\DebtClosures\ClosureActions;
use App\Filament\Resources\DebtClosures\DebtClosureResource;
use App\Modules\DebtConsolidation\Enums\ClosureStatus;
use App\Modules\DebtConsolidation\Models\DebtClosure;
use App\Modules\DebtConsolidation\Services\DebtPresenter;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DebtClosuresTable
{
    public static function configure(Table $table, bool $withCase = true): Table
    {
        return $table
            ->columns(array_values(array_filter([
                $withCase ? TextColumn::make('application.reference')->label('Case')->searchable()->url(fn (DebtClosure $record): string => DebtApplicationResource::getUrl('view', ['record' => $record->application]).'?relation=closure') : null,
                TextColumn::make('liability.lender_name')->label('Existing lender')->description(fn (DebtClosure $record): ?string => $record->liability?->account_reference_masked),
                TextColumn::make('closure_type')->label('Type')->badge()->color('gray'),
                TextColumn::make('status')->badge(),
                TextColumn::make('quotation.valid_until')->label('Quotation valid to')->date('j M Y')->placeholder('—')
                    ->color(fn (DebtClosure $record): ?string => $record->quotation?->isExpired() ? 'danger' : null),
                TextColumn::make('final_closure_amount')->label('Closure amount')->formatStateUsing(fn ($state): string => DebtPresenter::inr($state, true))->placeholder('—'),
                TextColumn::make('paid')->label('Confirmed paid')->state(fn (DebtClosure $record): string => DebtPresenter::inr($record->confirmedPaymentTotal(), true)),
                TextColumn::make('noc_received_at')->label('NOC received')->date('j M Y')->placeholder('—'),
                TextColumn::make('verified_at')->label('Verified')->date('j M Y')->placeholder('—'),
            ])))
            ->filters([SelectFilter::make('status')->options(ClosureStatus::class)->multiple()])
            ->recordActions([
                ViewAction::make()->url(fn (DebtClosure $record): string => DebtClosureResource::getUrl('view', ['record' => $record])),
                ClosureActions::recordPayment(),
                ClosureActions::updateStatus(),
            ]);
    }
}
