<?php

namespace App\Filament\Resources\GrievanceLevels\Pages;

use App\Filament\Resources\GrievanceLevels\GrievanceLevelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGrievanceLevels extends ListRecords
{
    protected static string $resource = GrievanceLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
