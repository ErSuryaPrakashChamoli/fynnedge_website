<?php

namespace App\Filament\Resources\EquifaxCreditRequests\Pages;

use App\Filament\Resources\EquifaxCreditRequests\EquifaxActions;
use App\Filament\Resources\EquifaxCreditRequests\EquifaxCreditRequestResource;
use App\Modules\Equifax\Models\EquifaxCreditRequest;
use Filament\Resources\Pages\ViewRecord;

/**
 * One Equifax request: its score, and the full report for those allowed to
 * see it. Every opening is recorded in the request's History.
 */
class ViewEquifaxCreditRequest extends ViewRecord
{
    protected static string $resource = EquifaxCreditRequestResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        /** @var EquifaxCreditRequest $request */
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
            EquifaxActions::downloadPdf(),
            EquifaxActions::viewRawJson(),
            EquifaxActions::retry(),
            EquifaxActions::reconcile(),
            EquifaxActions::fetchFreshFromRequest(),
        ];
    }
}
