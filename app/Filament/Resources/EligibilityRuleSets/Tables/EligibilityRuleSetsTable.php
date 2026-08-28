<?php

namespace App\Filament\Resources\EligibilityRuleSets\Tables;

use App\Modules\Eligibility\Enums\EligibilityRuleSetStatus;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EligibilityRuleSetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('lenderProduct.lender.name')->label('Lender')->searchable(),
                TextColumn::make('lenderProduct.loanProduct.name')->label('Product')->searchable(),
                TextColumn::make('version')->sortable(),
                TextColumn::make('rules_count')->counts('rules')->label('Rules')->alignCenter(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (EligibilityRuleSetStatus $state) => match ($state) {
                        EligibilityRuleSetStatus::Active => 'success',
                        EligibilityRuleSetStatus::Draft => 'warning',
                        EligibilityRuleSetStatus::Archived => 'gray',
                    }),
                TextColumn::make('effective_from')->date(),
                TextColumn::make('effective_until')->date(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('status')->options(EligibilityRuleSetStatus::class),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}
