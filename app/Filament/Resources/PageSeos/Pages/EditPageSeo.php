<?php

namespace App\Filament\Resources\PageSeos\Pages;

use App\Filament\Resources\PageSeos\PageSeoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPageSeo extends EditRecord
{
    protected static string $resource = PageSeoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
