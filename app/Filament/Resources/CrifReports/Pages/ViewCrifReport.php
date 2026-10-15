<?php

namespace App\Filament\Resources\CrifReports\Pages;

use App\Filament\Resources\CrifReports\CrifReportActions;
use App\Filament\Resources\CrifReports\CrifReportResource;
use App\Modules\CreditBureau\Models\CrifReport;
use Filament\Resources\Pages\ViewRecord;

/**
 * The full CRIF report. Every opening is recorded in the report's History, so
 * who looked at a customer's credit report is always answerable.
 */
class ViewCrifReport extends ViewRecord
{
    protected static string $resource = CrifReportResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        /** @var CrifReport $report */
        $report = $this->getRecord();
        $report->recordEvent('viewed');
    }

    /**
     * Polled by the report view while the request is pending or processing.
     */
    public function refreshReport(): void
    {
        $this->getRecord()->refresh();
    }

    protected function getHeaderActions(): array
    {
        return [
            CrifReportActions::downloadPdf(),
            CrifReportActions::viewRawJson(),
            CrifReportActions::retry(),
            CrifReportActions::reconcile(),
            CrifReportActions::fetchFreshFromReport(),
        ];
    }
}
