<?php

namespace App\Filament\Resources\CrifReports\Pages;

use App\Filament\Resources\CrifReports\CrifReportActions;
use App\Filament\Resources\CrifReports\CrifReportResource;
use Filament\Resources\Pages\ListRecords;

class ListCrifReports extends ListRecords
{
    protected static string $resource = CrifReportResource::class;

    /**
     * Redeclared WITHOUT ListRecords' #[Url(as: 'filters')]: the PAN filter
     * holds a full PAN, which must never land in the address bar, browser
     * history, a shared link or the web server's access log.
     *
     * @var array<string, mixed> | null
     */
    public ?array $tableFilters = null;

    protected function getHeaderActions(): array
    {
        return [
            CrifReportActions::fetchForCustomer(),
        ];
    }
}
