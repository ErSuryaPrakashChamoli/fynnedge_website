<?php

namespace App\Filament\Resources\VideoTestimonials\Tables;

use App\Enums\PublishStatus;
use App\Enums\VideoTestimonialSource;
use App\Support\Testimonials\VideoTestimonials;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VideoTestimonialsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('poster_path')
                    ->label('')
                    ->disk('public'),
                TextColumn::make('customer_name')
                    ->description(fn ($record): ?string => $record->headline)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('video_source')
                    ->label('Source')
                    ->badge(),
                TextColumn::make('placements')
                    ->label('Shown on')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => VideoTestimonials::label($state)),
                IconColumn::make('show_as_floating')
                    ->label('Pop-up')
                    ->boolean(),
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
                SelectFilter::make('video_source')
                    ->label('Source')
                    ->options(VideoTestimonialSource::class),
                SelectFilter::make('placements')
                    ->label('Page')
                    ->options(VideoTestimonials::options())
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
