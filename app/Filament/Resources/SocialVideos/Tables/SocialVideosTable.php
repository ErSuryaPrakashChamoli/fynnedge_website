<?php

namespace App\Filament\Resources\SocialVideos\Tables;

use App\Enums\PublishStatus;
use App\Enums\SocialPlatform;
use App\Support\SocialVideos\SocialVideos;
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
use Illuminate\Database\Eloquent\Builder;

class SocialVideosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('poster_path')
                    ->label('')
                    ->disk('public'),
                TextColumn::make('title')
                    ->description(fn ($record): ?string => $record->caption)
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('platform')
                    ->label('Posted on')
                    ->badge(),
                TextColumn::make('placements')
                    ->label('Shown on')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => SocialVideos::label($state)),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (PublishStatus $state) => match ($state) {
                        PublishStatus::Published => 'success',
                        PublishStatus::Draft => 'gray',
                    }),
                TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                SelectFilter::make('status')->options(PublishStatus::class),
                SelectFilter::make('platform')
                    ->label('Posted on')
                    ->options(SocialPlatform::class),
                SelectFilter::make('placements')
                    ->label('Page')
                    ->options(SocialVideos::options())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->forPlacements([$data['value']])
                        : $query),
                TrashedFilter::make(),
            ])
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
