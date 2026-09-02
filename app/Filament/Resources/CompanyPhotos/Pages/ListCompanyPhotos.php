<?php

namespace App\Filament\Resources\CompanyPhotos\Pages;

use App\Filament\Resources\CompanyPhotos\CompanyPhotoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCompanyPhotos extends ListRecords
{
    protected static string $resource = CompanyPhotoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
