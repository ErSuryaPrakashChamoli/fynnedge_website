<?php

namespace App\Filament\Resources\Articles\Tables;

use App\Enums\LoanCategory;
use App\Enums\PublishStatus;
use App\Models\Article;
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
use Illuminate\Support\Carbon;

class ArticlesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')
                    ->label('Cover')
                    ->disk('public'),
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('category')
                    ->badge()
                    ->placeholder('General'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (PublishStatus $state) => match ($state) {
                        PublishStatus::Published => 'success',
                        PublishStatus::Draft => 'warning',
                    }),
                /*
                 * The three date columns are deliberately NOT toggleable.
                 * Filament persists each admin's column layout in the session
                 * (HasColumnManager), and a column missing from that stored
                 * layout reads back as hidden — so anyone who opened this list
                 * before a date column existed would never see it, whatever
                 * the table now defaults to. A non-toggleable column skips
                 * that lookup entirely and always renders.
                 */
                TextColumn::make('published_at')
                    ->label('Published')
                    ->state(fn (Article $record): ?Carbon => $record->status === PublishStatus::Published ? $record->publishedOn() : null)
                    ->date('d M Y')
                    ->tooltip(fn (?Carbon $state): ?string => $state?->format('d M Y, H:i'))
                    ->placeholder('Not published')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->date('d M Y')
                    ->tooltip(fn (?Carbon $state): ?string => $state?->format('d M Y, H:i'))
                    ->sortable(),
                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->date('d M Y')
                    ->tooltip(fn (?Carbon $state): ?string => $state?->format('d M Y, H:i'))
                    ->placeholder('No expiry')
                    /*
                     * An expired article drops off the public site silently
                     * (Publishable::scopePublished), so a past date is flagged
                     * rather than reading like any other scheduled one.
                     */
                    ->color(fn (?Carbon $state): ?string => $state?->isPast() ? 'danger' : null)
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options(PublishStatus::class),
                SelectFilter::make('category')->options(LoanCategory::class),
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
