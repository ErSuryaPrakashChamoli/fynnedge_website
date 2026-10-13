<?php

namespace App\Filament\Resources\LenderProductRates\Pages;

use App\Filament\Resources\LenderProductRates\LenderProductRateResource;
use App\Filament\Resources\LenderProductRates\RateWorkflowActions;
use App\Models\LenderProductRate;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewLenderProductRate extends ViewRecord
{
    protected static string $resource = LenderProductRateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn (LenderProductRate $record): bool => $record->status->isEditable()),
            ...RateWorkflowActions::all(),
        ];
    }
}
