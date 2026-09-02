<?php

namespace App\Filament\Resources\LoanLandingPageSeo\Pages;

use App\Filament\Concerns\HasPreviewAction;
use App\Filament\Concerns\RestrictsUpdateToAllowedFields;
use App\Filament\Resources\LoanLandingPageSeo\LoanLandingPageSeoResource;
use App\Models\LoanLandingPage;
use Filament\Resources\Pages\EditRecord;

class EditLoanLandingPageSeo extends EditRecord
{
    use HasPreviewAction;
    use RestrictsUpdateToAllowedFields;

    protected static string $resource = LoanLandingPageSeoResource::class;

    /**
     * Empty on purpose: every field this page's form exposes lives on the
     * related SeoMeta record (saved independently via its own relationship,
     * see SeoFormSection) rather than as a direct LoanLandingPage column. No
     * top-level LoanLandingPage field should ever be written from this page.
     */
    protected static function allowedUpdateFields(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->previewAction(
                'loans.landing-pages.show',
                fn (LoanLandingPage $record) => ['loanProduct' => $record->loanProduct, 'landingPage' => $record],
            ),
        ];
    }
}
