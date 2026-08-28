<?php

namespace App\Filament\Resources\Lenders\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EmployerCategoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'employerCategories';

    protected static ?string $title = 'Employer categories';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                TextInput::make('key')
                    ->required()
                    ->helperText('Short code used in eligibility rules, e.g. "A".'),
                TextInput::make('label')
                    ->required()
                    ->helperText('What this shows as, e.g. "Category A — MNC / Listed".'),
                TextInput::make('order')
                    ->numeric()
                    ->default(0),
                Textarea::make('description')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                TextColumn::make('order')->sortable(),
                TextColumn::make('key'),
                TextColumn::make('label'),
                TextColumn::make('description')->limit(50),
            ])
            ->defaultSort('order')
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ])
            ->reorderable('order');
    }
}
