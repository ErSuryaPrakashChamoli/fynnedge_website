<?php

namespace App\Filament\Resources\DebtScoringConfigs\Pages;

use App\Filament\Resources\DebtScoringConfigs\DebtScoringConfigResource;
use App\Modules\DebtConsolidation\Enums\LenderPolicyStatus;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditDebtScoringConfig extends EditRecord
{
    protected static string $resource = DebtScoringConfigResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        abort_unless($this->getRecord()->status === LenderPolicyStatus::Draft, 403, 'Only drafts can be edited.');
    }

    /**
     * The draft may have been approved in another tab since this page opened.
     */
    protected function beforeSave(): void
    {
        if ($this->getRecord()->fresh()?->status !== LenderPolicyStatus::Draft) {
            Notification::make()->title('This version is no longer a draft, so it was not changed. Create a new version instead.')->danger()->send();

            $this->halt();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return [...$data, 'weights' => array_map('floatval', $data['weights']), 'thresholds' => array_map('floatval', $data['thresholds'])];
    }
}
