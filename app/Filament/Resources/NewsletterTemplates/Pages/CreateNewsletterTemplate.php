<?php

namespace App\Filament\Resources\NewsletterTemplates\Pages;

use App\Filament\Resources\NewsletterTemplates\NewsletterTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateNewsletterTemplate extends CreateRecord
{
    protected static string $resource = NewsletterTemplateResource::class;
}
