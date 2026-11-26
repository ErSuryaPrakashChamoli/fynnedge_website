<?php

namespace App\Filament\Resources\DebtLenderApplications\Tables;

use App\Filament\Resources\DebtApplications\DebtApplicationResource;
use App\Filament\Resources\DebtLenderApplications\DebtLenderApplicationResource;
use App\Filament\Resources\DebtLenderApplications\LenderApplicationActions;
use App\Modules\DebtConsolidation\Enums\LenderApplicationStatus;
use App\Modules\DebtConsolidation\Models\DebtLenderApplication;
use App\Modules\DebtConsolidation\Services\DebtPresenter;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DebtLenderApplicationsTable
{
    public static function configure(Table $table, bool $withCase = true): Table
    {
        return $table
            ->columns(array_values(array_filter([
                $withCase ? TextColumn::make('application.reference')->label('Case')->searchable()->url(fn (DebtLenderApplication $record): string => DebtApplicationResource::getUrl('view', ['record' => $record->application]).'?relation=lender-applications') : null,
                TextColumn::make('lender_name')->label('Lender')->searchable()->description(fn (DebtLenderApplication $record): string => $record->product_name),
                TextColumn::make('status')->badge(),
                TextColumn::make('lender_reference')->label('Lender ref.')->placeholder('—'),
                TextColumn::make('applied_amount')->label('Applied')->formatStateUsing(fn ($state): string => DebtPresenter::inr($state))->placeholder('—'),
                TextColumn::make('sanctioned_amount')->label('Sanctioned')->formatStateUsing(fn ($state): string => DebtPresenter::inr($state))->placeholder('—'),
                TextColumn::make('disbursed_amount')->label('Disbursed')->formatStateUsing(fn ($state): string => DebtPresenter::inr($state))->placeholder('—'),
                TextColumn::make('submitted_at')->label('Submitted')->date('j M Y')->placeholder('—'),
                TextColumn::make('updated_at')->label('Updated')->since(),
            ])))
            ->filters([SelectFilter::make('status')->options(LenderApplicationStatus::class)->multiple()])
            ->recordActions([
                ViewAction::make()->url(fn (DebtLenderApplication $record): string => DebtLenderApplicationResource::getUrl('view', ['record' => $record])),
                LenderApplicationActions::updateStatus(),
            ])
            ->defaultSort('updated_at', 'desc');
    }
}
