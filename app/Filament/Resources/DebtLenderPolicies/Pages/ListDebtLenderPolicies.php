<?php

namespace App\Filament\Resources\DebtLenderPolicies\Pages;

use App\Filament\Resources\DebtLenderPolicies\DebtLenderPolicyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDebtLenderPolicies extends ListRecords
{
    protected static string $resource = DebtLenderPolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
