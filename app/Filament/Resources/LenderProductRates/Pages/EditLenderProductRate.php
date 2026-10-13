<?php

namespace App\Filament\Resources\LenderProductRates\Pages;

use App\Enums\RateStatus;
use App\Filament\Resources\LenderProductRates\LenderProductRateResource;
use App\Models\LenderProductRate;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditLenderProductRate extends EditRecord
{
    protected static string $resource = LenderProductRateResource::class;

    /**
     * Only drafts are editable. Anything submitted, published or archived is
     * opened read-only instead — the model refuses the save anyway, and this
     * keeps an editor from filling in a form that cannot be saved.
     */
    public function mount(int|string $record): void
    {
        parent::mount($record);

        /** @var LenderProductRate $rate */
        $rate = $this->getRecord();

        if (! $rate->status->isEditable()) {
            Notification::make()
                ->title("This rate is {$rate->status->getLabel()} and can't be edited")
                ->body('Use "Edit as new version" to change it.')
                ->warning()
                ->send();

            $this->redirect(LenderProductRateResource::getUrl('view', ['record' => $rate]));
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->visible(fn (LenderProductRate $record): bool => $record->status === RateStatus::Draft),
        ];
    }
}
