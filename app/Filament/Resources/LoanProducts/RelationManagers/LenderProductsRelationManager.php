<?php

namespace App\Filament\Resources\LoanProducts\RelationManagers;

use App\Enums\LenderStatus;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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
                Select::make('status')
                    ->options(LenderStatus::class)
                    ->default(LenderStatus::Active)
                    ->required(),
                TextInput::make('min_amount')->numeric()->prefix('₹'),
                TextInput::make('max_amount')->numeric()->prefix('₹'),
                TextInput::make('min_tenure_months')->numeric()->suffix('months'),
                TextInput::make('max_tenure_months')->numeric()->suffix('months'),
                TextInput::make('interest_rate_from')->numeric()->suffix('%'),
                TextInput::make('interest_rate_to')->numeric()->suffix('%'),
                TextInput::make('processing_fee_note')->columnSpanFull(),
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
