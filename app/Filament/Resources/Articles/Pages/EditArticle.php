<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Filament\Concerns\HasPreviewAction;
use App\Filament\Resources\Articles\ArticleResource;
use App\Models\Article;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditArticle extends EditRecord
{
    use HasPreviewAction;

    protected static string $resource = ArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->previewAction('resources.show', fn (Article $record) => ['article' => $record]),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
