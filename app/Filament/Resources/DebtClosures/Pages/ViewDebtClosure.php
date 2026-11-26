<?php

namespace App\Filament\Resources\DebtClosures\Pages;

use App\Filament\Resources\DebtClosures\ClosureActions;
use App\Filament\Resources\DebtClosures\DebtClosureResource;
use Filament\Resources\Pages\ViewRecord;

class ViewDebtClosure extends ViewRecord
{
    protected static string $resource = DebtClosureResource::class;

    protected function getHeaderActions(): array
    {
        return [ClosureActions::recordPayment(), ClosureActions::updateStatus()];
    }
}
