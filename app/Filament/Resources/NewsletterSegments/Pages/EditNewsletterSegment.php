<?php

namespace App\Filament\Resources\NewsletterSegments\Pages;

use App\Filament\Resources\NewsletterSegments\NewsletterSegmentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditNewsletterSegment extends EditRecord
{
    protected static string $resource = NewsletterSegmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
