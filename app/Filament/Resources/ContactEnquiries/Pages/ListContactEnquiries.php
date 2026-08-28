<?php

namespace App\Filament\Resources\ContactEnquiries\Pages;

use App\Filament\Resources\ContactEnquiries\ContactEnquiryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListContactEnquiries extends ListRecords
{
    protected static string $resource = ContactEnquiryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
