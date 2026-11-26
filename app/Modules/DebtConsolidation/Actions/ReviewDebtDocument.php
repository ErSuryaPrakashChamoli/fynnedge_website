<?php

namespace App\Modules\DebtConsolidation\Actions;

use App\Modules\Applications\Enums\DocumentStatus;
use App\Modules\DebtConsolidation\Models\DebtDocument;
use Illuminate\Validation\ValidationException;

/**
 * Manual verification of a document by staff: verified, or rejected with
 * a reason the customer sees.
 */
class ReviewDebtDocument
{
    public function verify(DebtDocument $document, int $userId): DebtDocument
    {
        $document->forceFill(['status' => DocumentStatus::Verified, 'rejection_reason' => null, 'verified_by' => $userId, 'verified_at' => now()])->save();
        $document->application?->recordEvent('document_verified', ['document' => $document->public_id], $userId);

        return $document;
    }

    public function reject(DebtDocument $document, int $userId, string $reason): DebtDocument
    {
        if (blank($reason)) {
            throw ValidationException::withMessages(['rejection_reason' => 'Tell the customer why the document was not accepted.']);
        }

        $document->forceFill(['status' => DocumentStatus::Rejected, 'rejection_reason' => mb_substr($reason, 0, 255), 'verified_by' => $userId, 'verified_at' => now()])->save();
        $document->application?->recordEvent('document_rejected', ['document' => $document->public_id], $userId);

        return $document;
    }
}
