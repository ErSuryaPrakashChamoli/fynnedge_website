<?php

namespace App\Filament\Resources\PageFaqs\Pages;

use App\Filament\Resources\PageFaqs\PageFaqResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPageFaq extends EditRecord
{
    protected static string $resource = PageFaqResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
