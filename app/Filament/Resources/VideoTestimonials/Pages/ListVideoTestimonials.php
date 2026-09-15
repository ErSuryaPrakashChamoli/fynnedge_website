<?php

namespace App\Filament\Resources\VideoTestimonials\Pages;

use App\Filament\Resources\VideoTestimonials\VideoTestimonialResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVideoTestimonials extends ListRecords
{
    protected static string $resource = VideoTestimonialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
