<?php

namespace App\Filament\Resources\LoanProducts\RelationManagers;

use App\Enums\LenderStatus;
use App\Filament\Resources\LenderProducts\Schemas\LenderProductForm;
use App\Models\LenderProduct;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LenderProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'lenderProducts';

    protected static ?string $title = 'Lenders offering this product';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('lender_id')
                    ->relationship('lender', 'name')
                    ->required()
                    ->searchable(),
                ...LenderProductForm::offerAndEligibilityComponents(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('lender_id')
            ->columns([
                TextColumn::make('lender.name')
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
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (LenderStatus $state) => match ($state) {
                        LenderStatus::Active => 'success',
                        LenderStatus::Inactive => 'gray',
                    }),
            ])
            ->headerActions([
                CreateAction::make(),
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
