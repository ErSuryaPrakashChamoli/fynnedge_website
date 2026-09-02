<?php

namespace App\Filament\Resources\HowItWorksSteps\Pages;

use App\Filament\Resources\HowItWorksSteps\HowItWorksStepResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHowItWorksStep extends EditRecord
{
    protected static string $resource = HowItWorksStepResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
