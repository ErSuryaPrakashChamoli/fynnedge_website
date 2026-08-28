<?php

namespace App\Modules\Applications\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The website actively drives an application through Draft -> Submitted. States
 * from UnderReview onward happen inside the lender's/FYNN-ON's own processing —
 * this site has no underwriting engine and isn't meant to build one. Once a
 * FYNN-ON integration exists, those later states will be set by that sync, not
 * fabricated here. Until then an admin can set them manually as a stopgap.
 */
enum ApplicationStatus: string implements HasLabel
{
    case Draft = 'draft';
    case LenderSelected = 'lender_selected';
    case DocumentsPending = 'documents_pending';
    case DocumentsSubmitted = 'documents_submitted';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Sanctioned = 'sanctioned';
    case AgreementPending = 'agreement_pending';
    case DisbursalProcessing = 'disbursal_processing';
    case Disbursed = 'disbursed';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::LenderSelected => 'Lender selected',
            self::DocumentsPending => 'Documents pending',
            self::DocumentsSubmitted => 'Documents submitted',
            self::Submitted => 'Submitted',
            self::UnderReview => 'Under review',
            self::Sanctioned => 'Sanctioned',
            self::AgreementPending => 'Agreement pending',
            self::DisbursalProcessing => 'Disbursal processing',
            self::Disbursed => 'Disbursed',
            self::Rejected => 'Rejected',
            self::Withdrawn => 'Withdrawn',
            self::Cancelled => 'Cancelled',
        };
    }
}
