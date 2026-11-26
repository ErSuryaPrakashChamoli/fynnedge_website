<?php

namespace App\Filament\Resources\DebtScoringConfigs\Pages;

use App\Filament\Resources\DebtScoringConfigs\DebtScoringConfigResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDebtScoringConfigs extends ListRecords
{
    protected static string $resource = DebtScoringConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('New draft version')];
    }
}
