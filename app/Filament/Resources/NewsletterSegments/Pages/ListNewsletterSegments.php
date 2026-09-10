<?php

namespace App\Filament\Resources\NewsletterSegments\Pages;

use App\Filament\Resources\NewsletterSegments\NewsletterSegmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNewsletterSegments extends ListRecords
{
    protected static string $resource = NewsletterSegmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
