<?php

namespace App\Filament\Resources\DebtLenderApplications\Pages;

use App\Filament\Resources\DebtLenderApplications\DebtLenderApplicationResource;
use App\Filament\Resources\DebtLenderApplications\LenderApplicationActions;
use Filament\Resources\Pages\ViewRecord;

class ViewDebtLenderApplication extends ViewRecord
{
    protected static string $resource = DebtLenderApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [LenderApplicationActions::updateStatus()];
    }
}
