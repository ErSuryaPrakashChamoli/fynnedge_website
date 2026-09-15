<?php

namespace App\Filament\Resources\VideoTestimonials\Pages;

use App\Filament\Resources\VideoTestimonials\VideoTestimonialResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditVideoTestimonial extends EditRecord
{
    protected static string $resource = VideoTestimonialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
