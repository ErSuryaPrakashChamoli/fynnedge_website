<?php

namespace App\Filament\Resources\HowItWorksSteps\Pages;

use App\Filament\Resources\HowItWorksSteps\HowItWorksStepResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHowItWorksSteps extends ListRecords
{
    protected static string $resource = HowItWorksStepResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
