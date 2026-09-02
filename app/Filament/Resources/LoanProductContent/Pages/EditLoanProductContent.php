<?php

namespace App\Filament\Resources\LoanProductContent\Pages;

use App\Filament\Concerns\HasPreviewAction;
use App\Filament\Concerns\RestrictsUpdateToAllowedFields;
use App\Filament\Resources\LoanProductContent\LoanProductContentResource;
use App\Models\LoanProduct;
use Filament\Resources\Pages\EditRecord;

class EditLoanProductContent extends EditRecord
{
    use HasPreviewAction;
    use RestrictsUpdateToAllowedFields;

    protected static string $resource = LoanProductContentResource::class;

    /**
     * The one authoritative list of fields this restricted editor may ever
     * write — see RestrictsUpdateToAllowedFields. Keep this in sync with
     * LoanProductContentForm's fields; nothing outside this list reaches
     * LoanProduct::update() from this page, regardless of what a request
     * contains.
     */
    protected static function allowedUpdateFields(): array
    {
        return [
            'marketing_headline', 'summary', 'body', 'benefits', 'features',
            'eligibility_points', 'documents_required', 'process_steps',
            'image_path', 'image_alt', 'cta_label',
            'status', 'published_at', 'expires_at',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->previewAction('loans.show', fn (LoanProduct $record) => ['loanProduct' => $record]),
        ];
    }
}
