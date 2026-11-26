<?php

namespace App\Filament\Resources\DebtScenarios\Pages;

use App\Filament\Resources\DebtScenarios\DebtScenarioResource;
use App\Modules\DebtConsolidation\Enums\ActorType;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDebtScenarios extends ListRecords
{
    protected static string $resource = DebtScenarioResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->mutateDataUsing(fn (array $data): array => [...$data, 'created_by_type' => ActorType::Staff, 'created_by' => auth()->id()])];
    }
}
