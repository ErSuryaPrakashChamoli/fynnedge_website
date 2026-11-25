<?php

namespace App\Filament\Resources\Authors\Tables;

use App\Models\Author;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AuthorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('articles'))
            ->defaultSort('name')
            ->columns([
                ImageColumn::make('photo_path')
                    ->label('Photo')
                    ->disk('public')
                    ->circular()
                    ->defaultImageUrl(null),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn (Author $record): ?string => $record->designation),
                TextColumn::make('slug')
                    ->label('Author page')
                    ->prefix('/resources/authors/')
                    ->limit(28)
                    ->tooltip(fn (Author $record): string => '/resources/authors/'.$record->slug)
                    ->color('gray'),
                TextColumn::make('articles_count')
                    ->label('Articles')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('Last updated')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('viewPage')
                    ->label('Open author page')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->iconButton()
                    ->color('gray')
                    ->url(fn (Author $record): string => route('resources.authors.show', $record->slug))
                    ->openUrlInNewTab()
                    ->visible(fn (Author $record): bool => $record->is_active),
            ])
            ->emptyStateHeading('No authors yet')
            ->emptyStateDescription('Add the people who write FynnEdge articles; then pick one in each article\'s "Author" field.');
    }
}
