<?php

namespace App\Filament\Resources\NotFoundUrls\Tables;

use App\Filament\Resources\Redirects\RedirectResource;
use App\Models\NotFoundUrl;
use App\Models\Redirect;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NotFoundUrlsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('path')
                    ->label('Path')
                    ->searchable()
                    ->copyable()
                    ->wrap()
                    ->description(fn (NotFoundUrl $record): ?string => isset(Redirect::activeMap()[$record->path])
                        ? 'A rule now covers this path'
                        : null),
                TextColumn::make('hits')
                    ->label('Requests')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('internal_referrals')
                    ->label('From this site')
                    ->tooltip('Requests that followed a link on this site: a broken internal link to fix at its source')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('crawler_hits')
                    ->label('Search crawlers')
                    ->tooltip('Requests from Googlebot, Bingbot or Applebot')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('last_referrer')
                    ->label('Last linked from')
                    ->placeholder('Unknown')
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('first_seen_at')
                    ->label('First seen')
                    ->dateTime('d M Y')
                    ->sortable(),
                TextColumn::make('last_seen_at')
                    ->label('Last seen')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('hits', 'desc')
            ->filters([
                Filter::make('linked_internally')
                    ->label('Linked from this site')
                    ->query(fn (Builder $query): Builder => $query->where('internal_referrals', '>', 0)),
                Filter::make('crawled')
                    ->label('Requested by search crawlers')
                    ->query(fn (Builder $query): Builder => $query->where('crawler_hits', '>', 0)),
                Filter::make('new')
                    ->label('First seen in the last 7 days')
                    ->query(fn (Builder $query): Builder => $query->where('first_seen_at', '>=', now()->subDays(7))),
                Filter::make('recent')
                    ->label('Seen in the last 7 days')
                    ->query(fn (Builder $query): Builder => $query->where('last_seen_at', '>=', now()->subDays(7))),
            ])
            ->recordActions([
                Action::make('addRule')
                    ->label('Add rule')
                    ->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->url(fn (NotFoundUrl $record): string => RedirectResource::getUrl('create', ['source_path' => $record->path]))
                    ->visible(fn (NotFoundUrl $record): bool => RedirectResource::canCreate()
                        && ! isset(self::pathsWithARule()[$record->path])),
                DeleteAction::make()
                    ->label('Dismiss')
                    ->modalDescription('Removes this row from the list. If the path is requested again it is recorded again.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Dismiss selected'),
                ]),
            ]);
    }

    /**
     * Every path that already has a row in Redirects, active or not: a second
     * row for it would be refused, so "Add rule" is hidden there. One query
     * per request rather than one per table row.
     *
     * @return array<string, int>
     */
    private static function pathsWithARule(): array
    {
        return once(fn (): array => Redirect::query()->pluck('source_path')->flip()->all());
    }
}
