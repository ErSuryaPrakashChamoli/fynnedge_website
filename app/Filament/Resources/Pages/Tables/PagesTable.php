<?php

namespace App\Filament\Resources\Pages\Tables;

use App\Enums\PublishStatus;
use App\Models\Page;
use App\Support\Seo\Sitemap;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('seoMeta'))
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn (Page $record): string => Sitemap::pageUrl($record->slug, absolute: false) ?? '/'.$record->slug.' — no public address'),
                TextColumn::make('page_type')
                    ->label('Type')
                    ->state(fn (Page $record): string => in_array($record->slug, Sitemap::publicPageSlugs(), true) ? 'Fixed page' : 'General page')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Fixed page' ? 'gray' : 'info')
                    ->tooltip(fn (string $state): string => $state === 'Fixed page'
                        ? 'Linked from the footer or menus; its address is locked.'
                        : 'Created here; served at its own address. Add it to Footer Quick Links to link it from every page.'),
                TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (PublishStatus $state) => match ($state) {
                        PublishStatus::Published => 'success',
                        PublishStatus::Draft => 'warning',
                    }),
                TextColumn::make('seo_status')
                    ->label('SEO')
                    ->state(fn (Page $record): string => filled($record->seoMeta?->title) && filled($record->seoMeta?->description) ? 'Complete' : 'Missing')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Complete' ? 'success' : 'danger')
                    ->tooltip('Search title and description filled in the page\'s SEO section.'),
                TextColumn::make('updated_at')
                    ->label('Last updated')
                    ->since()
                    ->dateTimeTooltip('d M Y, h:i A')
                    ->sortable(),
                TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('title')
            ->filters([
                SelectFilter::make('status')->options(PublishStatus::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    Action::make('viewLive')
                        ->label('View on website')
                        ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                        ->url(fn (Page $record): ?string => Sitemap::pageUrl($record->slug))
                        ->openUrlInNewTab()
                        ->visible(fn (Page $record): bool => $record->isCurrentlyPublished() && Sitemap::pageUrl($record->slug) !== null),
                    Action::make('publish')
                        ->label('Publish now')
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn (Page $record): bool => $record->status !== PublishStatus::Published && (bool) auth()->user()?->can('update', $record))
                        ->action(function (Page $record): void {
                            $record->update(['status' => PublishStatus::Published, 'published_at' => $record->published_at ?? now()]);

                            Notification::make()->title('Page published')->success()->send();
                        }),
                    Action::make('unpublish')
                        ->label('Unpublish (back to draft)')
                        ->icon(Heroicon::OutlinedEyeSlash)
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalDescription(fn (Page $record): string => in_array($record->slug, Sitemap::publicPageSlugs(), true)
                            ? 'This page is linked from the website (footer/menus). Unpublishing hides it and its links until it is published again.'
                            : 'Visitors will get "page not found" at this address until it is published again.')
                        ->visible(fn (Page $record): bool => $record->status === PublishStatus::Published && (bool) auth()->user()?->can('update', $record))
                        ->action(function (Page $record): void {
                            $record->update(['status' => PublishStatus::Draft]);

                            Notification::make()->title('Page moved back to draft')->success()->send();
                        }),
                ]),
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
