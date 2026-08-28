<?php

namespace App\Filament\Resources\DocumentRequirements\Tables;

use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DocumentRequirementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('lenderProduct.lender.name')->label('Lender')->searchable(),
                TextColumn::make('lenderProduct.loanProduct.name')->label('Product')->searchable(),
                TextColumn::make('documentType.label')->label('Document')->searchable(),
                IconColumn::make('is_required')->label('Required')->boolean(),
                TextColumn::make('order')->sortable(),
            ])
            ->defaultSort('order')
            ->filters([
                SelectFilter::make('lender_product_id')
                    ->label('Lender product')
                    ->relationship('lenderProduct', 'id')
                    ->getOptionLabelFromRecordUsing(
                        fn ($record) => "{$record->lender->name} — {$record->loanProduct->name}",
                    )
                    ->searchable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}
