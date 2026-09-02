<?php

namespace App\Filament\Resources\CalculatorPages\Pages;

use App\Filament\Resources\CalculatorPages\CalculatorPageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCalculatorPages extends ListRecords
{
    protected static string $resource = CalculatorPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
