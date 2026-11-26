<?php

namespace App\Filament\Resources\DebtLenderPolicies\Pages;

use App\Filament\Resources\DebtLenderPolicies\DebtLenderPolicyResource;
use App\Modules\DebtConsolidation\Enums\LenderPolicyStatus;
use Filament\Resources\Pages\CreateRecord;

class CreateDebtLenderPolicy extends CreateRecord
{
    protected static string $resource = DebtLenderPolicyResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$data, 'status' => LenderPolicyStatus::Draft, 'created_by' => auth()->id()];
    }
}
