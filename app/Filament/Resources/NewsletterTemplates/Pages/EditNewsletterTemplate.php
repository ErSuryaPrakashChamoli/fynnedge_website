<?php

namespace App\Filament\Resources\NewsletterTemplates\Pages;

use App\Filament\Resources\NewsletterTemplates\NewsletterTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditNewsletterTemplate extends EditRecord
{
    protected static string $resource = NewsletterTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
