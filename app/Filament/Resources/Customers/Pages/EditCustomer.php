<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\CrifReports\CrifReportActions;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\EquifaxCreditRequests\EquifaxActions;
use App\Filament\Resources\ExperianCreditRequests\ExperianActions;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CrifReportActions::viewExisting(),
            CrifReportActions::fetch(),
            CrifReportActions::fetchFresh(),
            EquifaxActions::group(),
            ExperianActions::group(),
            DeleteAction::make(),
        ];
    }
}
