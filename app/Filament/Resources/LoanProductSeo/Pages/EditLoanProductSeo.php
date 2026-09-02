<?php

namespace App\Filament\Resources\LoanProductSeo\Pages;

use App\Filament\Concerns\HasPreviewAction;
use App\Filament\Concerns\RestrictsUpdateToAllowedFields;
use App\Filament\Resources\LoanProductSeo\LoanProductSeoResource;
use App\Models\LoanProduct;
use Filament\Resources\Pages\EditRecord;

class EditLoanProductSeo extends EditRecord
{
    use HasPreviewAction;
    use RestrictsUpdateToAllowedFields;

    protected static string $resource = LoanProductSeoResource::class;

    /**
     * Empty on purpose: every field this page's form exposes lives on the
     * related SeoMeta record (saved independently via its own relationship,
     * see SeoFormSection) rather than as a direct LoanProduct column. No
     * top-level LoanProduct field should ever be written from this page.
     */
    protected static function allowedUpdateFields(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->previewAction('loans.show', fn (LoanProduct $record) => ['loanProduct' => $record]),
        ];
    }
}
