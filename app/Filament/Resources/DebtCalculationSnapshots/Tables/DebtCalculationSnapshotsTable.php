<?php

namespace App\Filament\Resources\DebtCalculationSnapshots\Tables;

use App\Filament\Resources\DebtApplications\DebtApplicationResource;
use App\Filament\Resources\DebtCalculationSnapshots\DebtCalculationSnapshotResource;
use App\Modules\DebtConsolidation\Enums\SuitabilityBand;
use App\Modules\DebtConsolidation\Models\DebtCalculationSnapshot;
use App\Modules\DebtConsolidation\Services\DebtPresenter;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DebtCalculationSnapshotsTable
{
    public static function configure(Table $table, bool $withCase = true): Table
    {
        return $table
            ->columns(array_values(array_filter([
                $withCase ? TextColumn::make('application.reference')->label('Case')->placeholder('Calculator (no case)')->searchable()
                    ->url(fn (DebtCalculationSnapshot $record): ?string => $record->application ? DebtApplicationResource::getUrl('view', ['record' => $record->application]) : null) : null,
                TextColumn::make('created_at')->label('Calculated')->dateTime('j M Y, g:i a', 'Asia/Kolkata')->sortable(),
                TextColumn::make('kind')->badge()->color('gray'),
                TextColumn::make('total_outstanding')->label('Total debt')->formatStateUsing(fn ($state): string => DebtPresenter::inr($state))->alignEnd(),
                TextColumn::make('net_benefit')->label('Net benefit')->formatStateUsing(fn ($state): string => DebtPresenter::inr($state))->placeholder('—')->alignEnd()
                    ->color(fn ($state): ?string => $state === null ? null : ((float) $state > 0 ? 'success' : 'danger')),
                TextColumn::make('suitability_score')->label('Score')->placeholder('—')->alignEnd(),
                TextColumn::make('suitability_band')->label('Suitability')->badge()->placeholder('—'),
                TextColumn::make('formula_version')->label('Formula')->badge()->color('gray'),
                TextColumn::make('scoring_version')->label('Scoring')->placeholder('—'),
                TextColumn::make('created_by_type')->label('By')->badge()->color('gray')->description(fn (DebtCalculationSnapshot $record): ?string => $record->creator?->name),
            ])))
            ->filters([SelectFilter::make('suitability_band')->label('Suitability')->options(SuitabilityBand::class)])
            ->recordActions([ViewAction::make()->url(fn (DebtCalculationSnapshot $record): string => DebtCalculationSnapshotResource::getUrl('view', ['record' => $record]))])
            ->defaultSort('created_at', 'desc');
    }
}
