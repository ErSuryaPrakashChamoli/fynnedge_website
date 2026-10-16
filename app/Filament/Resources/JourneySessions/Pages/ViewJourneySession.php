<?php

namespace App\Filament\Resources\JourneySessions\Pages;

use App\Filament\Resources\CrifReports\CrifReportActions;
use App\Filament\Resources\EquifaxCreditRequests\EquifaxActions;
use App\Filament\Resources\ExperianCreditRequests\ExperianActions;
use App\Filament\Resources\JourneySessions\JourneySessionResource;
use Filament\Resources\Pages\ViewRecord;

class ViewJourneySession extends ViewRecord
{
    protected static string $resource = JourneySessionResource::class;

    /**
     * CRIF actions act on the application's customer, prefilled from the
     * application's own answers and its website consent.
     */
    protected function getHeaderActions(): array
    {
        return [
            CrifReportActions::viewExisting(),
            CrifReportActions::fetch(),
            CrifReportActions::fetchFresh(),
            EquifaxActions::group(),
            ExperianActions::group(),
        ];
    }
}
