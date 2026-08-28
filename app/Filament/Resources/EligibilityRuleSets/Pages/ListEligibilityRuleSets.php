<?php

namespace App\Filament\Resources\EligibilityRuleSets\Pages;

use App\Filament\Resources\EligibilityRuleSets\EligibilityRuleSetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEligibilityRuleSets extends ListRecords
{
    protected static string $resource = EligibilityRuleSetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
