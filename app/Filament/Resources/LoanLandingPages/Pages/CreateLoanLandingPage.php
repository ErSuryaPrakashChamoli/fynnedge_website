<?php

namespace App\Filament\Resources\LoanLandingPages\Pages;

use App\Filament\Resources\LoanLandingPages\LoanLandingPageResource;
use Filament\Resources\Pages\CreateRecord;
use Livewire\Attributes\Url;

class CreateLoanLandingPage extends CreateRecord
{
    protected static string $resource = LoanLandingPageResource::class;

    /**
     * "campaign" when opened from Create a Page → Campaign landing page.
     */
    #[Url]
    public ?string $purpose = null;

    /**
     * A campaign page is meant for ads, not search: start its SEO indexing at
     * "Noindex, follow" so it does not compete with the product's SEO pages.
     * The editor can still change it before saving.
     */
    protected function afterFill(): void
    {
        if ($this->purpose === 'campaign') {
            $this->data['seoMeta']['robots'] = 'noindex, follow';
        }
    }
}
