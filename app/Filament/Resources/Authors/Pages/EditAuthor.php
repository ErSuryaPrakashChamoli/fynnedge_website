<?php

namespace App\Filament\Resources\Authors\Pages;

use App\Filament\Resources\Authors\AuthorResource;
use App\Models\Author;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditAuthor extends EditRecord
{
    protected static string $resource = AuthorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewPage')
                ->label('View author page')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (Author $record): string => route('resources.authors.show', $record->slug))
                ->openUrlInNewTab()
                ->visible(fn (Author $record): bool => $record->is_active),
            DeleteAction::make()
                ->modalDescription(fn (Author $record): string => $record->articles()->count() > 0
                    ? "{$record->articles()->count()} article(s) credit this author; they will show no byline. To hide the author but keep the link, switch \"Active\" off instead."
                    : 'This author is not credited on any article.'),
        ];
    }
}
