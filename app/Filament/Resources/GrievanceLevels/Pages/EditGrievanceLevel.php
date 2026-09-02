<?php

namespace App\Filament\Resources\GrievanceLevels\Pages;

use App\Filament\Resources\GrievanceLevels\GrievanceLevelResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGrievanceLevel extends EditRecord
{
    protected static string $resource = GrievanceLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
