<?php

namespace App\Filament\Resources\CalculatorPages\Pages;

use App\Filament\Resources\CalculatorPages\CalculatorPageResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCalculatorPage extends EditRecord
{
    protected static string $resource = CalculatorPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
