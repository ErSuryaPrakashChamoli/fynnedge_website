<?php

namespace App\Filament\Resources\EligibilityRuleSets\Pages;

use App\Filament\Resources\EligibilityRuleSets\EligibilityRuleSetResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateEligibilityRuleSet extends CreateRecord
{
    protected static string $resource = EligibilityRuleSetResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Filament::auth()->id();

        return $data;
    }
}
