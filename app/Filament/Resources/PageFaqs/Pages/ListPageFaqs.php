<?php

namespace App\Filament\Resources\PageFaqs\Pages;

use App\Filament\Resources\PageFaqs\PageFaqResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPageFaqs extends ListRecords
{
    protected static string $resource = PageFaqResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
