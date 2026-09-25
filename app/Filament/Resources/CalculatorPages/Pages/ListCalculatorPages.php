<?php

namespace App\Filament\Resources\CalculatorPages\Pages;

use App\Filament\Resources\CalculatorPages\CalculatorPageResource;
use App\Models\CalculatorPage;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCalculatorPages extends ListRecords
{
    protected static string $resource = CalculatorPageResource::class;

    /**
     * Lists every calculator in the menu, not only the ones already edited,
     * so each page's heading is one "Edit" click away.
     */
    public function mount(): void
    {
        CalculatorPage::ensureRowForEveryCalculator();

        parent::mount();
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
