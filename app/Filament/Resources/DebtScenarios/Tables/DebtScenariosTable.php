<?php

namespace App\Filament\Resources\DebtScenarios\Tables;

use App\Filament\Resources\DebtApplications\DebtApplicationResource;
use App\Modules\DebtConsolidation\Models\DebtScenario;
use App\Modules\DebtConsolidation\Services\DebtPresenter;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DebtScenariosTable
{
    public static function configure(Table $table, bool $withCase = true): Table
    {
        return $table
            ->columns(array_values(array_filter([
                $withCase ? TextColumn::make('application.reference')->label('Case')->searchable()->url(fn (DebtScenario $record): string => DebtApplicationResource::getUrl('view', ['record' => $record->application]).'?relation=savings') : null,
                TextColumn::make('label')->searchable()->description(fn (DebtScenario $record): ?string => $record->financing_route?->getLabel()),
                TextColumn::make('lenderProduct.lender.name')->label('Lender')->placeholder('Generic'),
                TextColumn::make('annual_interest_rate')->label('Rate')->suffix('%')->alignEnd(),
                TextColumn::make('tenure_months')->label('Months')->alignEnd(),
                TextColumn::make('processing_fee_percent')->label('Fee')->suffix('%')->alignEnd(),
                TextColumn::make('loan_amount_override')->label('Sanctioned')->formatStateUsing(fn ($state): string => DebtPresenter::inr($state))->placeholder('Sized to closures'),
                IconColumn::make('is_preferred')->label('Preferred')->boolean(),
                TextColumn::make('created_by_type')->label('Added by')->badge()->color('gray'),
            ])))
            ->recordActions([EditAction::make()]);
    }
}
