<?php

namespace App\Filament\Resources\PageFaqs\Tables;

use App\Enums\PublishStatus;
use App\Support\Faqs\FaqPlacements;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PageFaqsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('question')
                    ->searchable()
                    ->limit(60),
                TextColumn::make('placements')
                    ->label('Shown on')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => FaqPlacements::label($state)),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (PublishStatus $state) => match ($state) {
                        PublishStatus::Published => 'success',
                        PublishStatus::Draft => 'warning',
                    }),
                TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),
                TextColumn::make('expires_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->filters([
                SelectFilter::make('status')->options(PublishStatus::class),
                SelectFilter::make('placements')
                    ->label('Page')
                    ->options(FaqPlacements::options())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->forPlacement($data['value'])
                        : $query),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->reorderable('sort_order');
    }
}
