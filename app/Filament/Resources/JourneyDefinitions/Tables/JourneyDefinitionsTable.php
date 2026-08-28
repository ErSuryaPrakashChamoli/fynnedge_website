<?php

namespace App\Filament\Resources\JourneyDefinitions\Tables;

use App\Modules\Journey\Enums\JourneyDefinitionStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class JourneyDefinitionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('loanProduct.name')
                    ->label('Loan product')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('version')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('steps_count')
                    ->label('Steps')
                    ->counts('steps')
                    ->alignCenter(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (JourneyDefinitionStatus $state) => match ($state) {
                        JourneyDefinitionStatus::Active => 'success',
                        JourneyDefinitionStatus::Draft => 'warning',
                        JourneyDefinitionStatus::Archived => 'gray',
                    }),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('loan_product_id')
            ->filters([
                SelectFilter::make('status')->options(JourneyDefinitionStatus::class),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
