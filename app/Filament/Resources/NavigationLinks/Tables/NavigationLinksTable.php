<?php

namespace App\Filament\Resources\NavigationLinks\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class NavigationLinksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')->searchable()->sortable(),
                TextColumn::make('location')->badge(),
                TextColumn::make('route_name')->label('Route')->placeholder('—'),
                TextColumn::make('url')->label('URL')->placeholder('—')->limit(40),
                IconColumn::make('is_external')->boolean()->label('External'),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('sort_order')->label('Order')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                SelectFilter::make('location')->options(['footer' => 'Footer']),
                TernaryFilter::make('is_active'),
            ])
            ->reorderable('sort_order')
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
