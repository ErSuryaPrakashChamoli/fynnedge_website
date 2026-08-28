<?php

namespace App\Filament\Resources\EligibilityRuleSets\Pages;

use App\Filament\Resources\EligibilityRuleSets\EligibilityRuleSetResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEligibilityRuleSet extends EditRecord
{
    protected static string $resource = EligibilityRuleSetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
