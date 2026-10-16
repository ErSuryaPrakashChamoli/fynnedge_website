<?php

namespace App\Filament\Resources\CibilCreditRequests\Pages;

use App\Filament\Resources\CibilCreditRequests\CibilActions;
use App\Filament\Resources\CibilCreditRequests\CibilCreditRequestResource;
use App\Modules\Cibil\Models\CibilCreditRequest;
use Filament\Resources\Pages\ViewRecord;

/**
 * One CIBIL request: its score, and the full report for those allowed to
 * see it. Every opening is recorded in the request's History.
 */
class ViewCibilCreditRequest extends ViewRecord
{
    protected static string $resource = CibilCreditRequestResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        /** @var CibilCreditRequest $request */
        $request = $this->getRecord();
        $request->recordEvent('viewed');
    }

    /**
     * Polled by the report view while the request is pending or processing.
     */
    public function refreshRequest(): void
    {
        $this->getRecord()->refresh();
    }

    protected function getHeaderActions(): array
    {
        return [
            CibilActions::downloadPdf(),
            CibilActions::requestPdfFromRequest(),
            CibilActions::viewRawJson(),
            CibilActions::retry(),
            CibilActions::reconcile(),
            CibilActions::fetchFreshFromRequest(),
        ];
    }
}
