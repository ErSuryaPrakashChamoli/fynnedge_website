<?php

namespace App\Filament\Resources\LoanLandingPageContent\Pages;

use App\Filament\Concerns\HasPreviewAction;
use App\Filament\Concerns\RestrictsUpdateToAllowedFields;
use App\Filament\Resources\LoanLandingPageContent\LoanLandingPageContentResource;
use App\Models\LoanLandingPage;
use Filament\Resources\Pages\EditRecord;

class EditLoanLandingPageContent extends EditRecord
{
    use HasPreviewAction;
    use RestrictsUpdateToAllowedFields;

    protected static string $resource = LoanLandingPageContentResource::class;

    /**
     * The one authoritative list of fields this restricted editor may ever
     * write — see RestrictsUpdateToAllowedFields. Keep this in sync with
     * LoanLandingPageContentForm's fields; nothing outside this list
     * (loan_product_id, group, slug, amount, sort_order included) reaches
     * LoanLandingPage::update() from this page, regardless of what a request
     * contains.
     */
    protected static function allowedUpdateFields(): array
    {
        return [
            'title', 'excerpt', 'body', 'cta_label',
            'status', 'published_at', 'expires_at',
        ];
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
