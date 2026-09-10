<?php

namespace App\Filament\Resources\NewsletterTemplates\Pages;

use App\Filament\Resources\NewsletterTemplates\NewsletterTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNewsletterTemplates extends ListRecords
{
    protected static string $resource = NewsletterTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
