<?php

namespace App\Filament\Resources\LenderProducts\Tables;

use App\Enums\LenderStatus;
use App\Models\LenderProduct;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LenderProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('lender.name')
                    ->label('Lender')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('loanProduct.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('min_amount')->money('INR')->sortable(),
                TextColumn::make('max_amount')->money('INR')->sortable(),
                TextColumn::make('interest_rate_from')->suffix('%')->label('Rate from'),
                TextColumn::make('interest_rate_to')->suffix('%')->label('Rate to'),
                TextColumn::make('processing_fee')
                    ->label('Fee')
                    ->getStateUsing(fn (LenderProduct $record) => $record->processingFeeDisplay())
                    ->toggleable(),
                TextColumn::make('min_credit_score')->label('Min score')->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (LenderStatus $state) => match ($state) {
                        LenderStatus::Active => 'success',
                        LenderStatus::Inactive => 'gray',
                    }),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('loan_product_id')
                    ->label('Product')
                    ->relationship('loanProduct', 'name'),
                SelectFilter::make('status')
                    ->options(LenderStatus::class),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}
