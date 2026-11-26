<?php

namespace App\Filament\Resources\DebtScoringConfigs\Pages;

use App\Filament\Resources\DebtScoringConfigs\DebtScoringConfigResource;
use App\Modules\DebtConsolidation\Enums\LenderPolicyStatus;
use Filament\Resources\Pages\CreateRecord;

class CreateDebtScoringConfig extends CreateRecord
{
    protected static string $resource = DebtScoringConfigResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$data, 'status' => LenderPolicyStatus::Draft, 'created_by' => auth()->id(), 'weights' => array_map('floatval', $data['weights']), 'thresholds' => array_map('floatval', $data['thresholds'])];
    }
}
