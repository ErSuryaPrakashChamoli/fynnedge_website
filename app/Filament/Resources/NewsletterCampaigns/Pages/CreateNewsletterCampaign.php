<?php

namespace App\Filament\Resources\NewsletterCampaigns\Pages;

use App\Filament\Resources\NewsletterCampaigns\NewsletterCampaignResource;
use App\Modules\Newsletter\Enums\CampaignStatus;
use Filament\Resources\Pages\CreateRecord;

class CreateNewsletterCampaign extends CreateRecord
{
    protected static string $resource = NewsletterCampaignResource::class;

    /**
     * A new campaign is always a draft, and always attributed to whoever made
     * it — sending is a separate, explicit action from the campaign list.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [
            ...$data,
            'status' => CampaignStatus::Draft,
            'created_by' => auth()->id(),
        ];
    }
}
