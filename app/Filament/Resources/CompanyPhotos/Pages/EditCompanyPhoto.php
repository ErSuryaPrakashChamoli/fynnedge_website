<?php

namespace App\Filament\Resources\CompanyPhotos\Pages;

use App\Filament\Resources\CompanyPhotos\CompanyPhotoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCompanyPhoto extends EditRecord
{
    protected static string $resource = CompanyPhotoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
