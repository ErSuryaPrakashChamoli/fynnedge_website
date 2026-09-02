<?php

namespace App\Filament\Resources\CalculatorPages\Pages;

use App\Filament\Resources\CalculatorPages\CalculatorPageResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCalculatorPage extends CreateRecord
{
    protected static string $resource = CalculatorPageResource::class;
}
