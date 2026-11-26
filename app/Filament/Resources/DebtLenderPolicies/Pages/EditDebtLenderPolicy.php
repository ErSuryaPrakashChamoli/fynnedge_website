<?php

namespace App\Filament\Resources\DebtLenderPolicies\Pages;

use App\Filament\Resources\DebtLenderPolicies\DebtLenderPolicyResource;
use App\Modules\DebtConsolidation\Enums\LenderPolicyStatus;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditDebtLenderPolicy extends EditRecord
{
    protected static string $resource = DebtLenderPolicyResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        abort_unless($this->getRecord()->status === LenderPolicyStatus::Draft, 403, 'Approved policies are changed by creating a new version.');
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
}
