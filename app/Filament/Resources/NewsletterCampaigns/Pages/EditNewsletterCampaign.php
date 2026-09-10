<?php

namespace App\Filament\Resources\NewsletterCampaigns\Pages;

use App\Filament\Resources\NewsletterCampaigns\NewsletterCampaignResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditNewsletterCampaign extends EditRecord
{
    protected static string $resource = NewsletterCampaignResource::class;

    /**
     * A campaign that is sending or already sent is a historical record of what
     * people received — editing it would make the archive disagree with the
     * inbox, so the edit page refuses to open at all.
     */
    public function mount(int|string $record): void
    {
        parent::mount($record);

        if (! $this->getRecord()->isEditable()) {
            $this->redirect(static::getResource()::getUrl('index'));
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->visible(fn (): bool => $this->getRecord()->isEditable()),
        ];
    }
}
