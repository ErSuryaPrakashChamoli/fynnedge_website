<?php

namespace App\Filament\Resources\ExperianCreditRequests\Pages;

use App\Filament\Resources\ExperianCreditRequests\ExperianActions;
use App\Filament\Resources\ExperianCreditRequests\ExperianCreditRequestResource;
use App\Modules\Experian\Models\ExperianCreditRequest;
use Filament\Resources\Pages\ViewRecord;

/**
 * One Experian request: its score, and the full report for those allowed to
 * see it. Every opening is recorded in the request's History.
 */
class ViewExperianCreditRequest extends ViewRecord
{
    protected static string $resource = ExperianCreditRequestResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        /** @var ExperianCreditRequest $request */
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
            ExperianActions::downloadPdf(),
            ExperianActions::requestPdfFromRequest(),
            ExperianActions::viewRawJson(),
            ExperianActions::retry(),
            ExperianActions::reconcile(),
            ExperianActions::fetchFreshFromRequest(),
        ];
    }
}
