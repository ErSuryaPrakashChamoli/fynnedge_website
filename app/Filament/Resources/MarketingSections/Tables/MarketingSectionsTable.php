<?php

namespace App\Filament\Resources\MarketingSections\Tables;

use App\Enums\PublishStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class MarketingSectionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')->label('')->circular(),
                TextColumn::make('placement')
                    ->badge()
                    ->searchable(),
                TextColumn::make('heading')
                    ->searchable()
                    ->limit(40),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (PublishStatus $state) => match ($state) {
                        PublishStatus::Published => 'success',
                        PublishStatus::Draft => 'gray',
                    }),
                TextColumn::make('published_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('expires_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sort_order')->label('Order')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                SelectFilter::make('status')->options(PublishStatus::class),
                TrashedFilter::make(),
            ])
            ->reorderable('sort_order')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
