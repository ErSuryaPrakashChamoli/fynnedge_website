<?php

namespace App\Modules\DebtConsolidation\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The lender's own decision on an application FynnEdge facilitated. Every
 * state from Submitted onward is recorded by staff from what the lender
 * communicated — never inferred or simulated by this system.
 */
enum LenderApplicationStatus: string implements HasColor, HasLabel
{
    case NotSubmitted = 'not_submitted';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case AdditionalDocumentsRequired = 'additional_documents_required';
    case CreditAssessment = 'credit_assessment';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case SanctionLetterIssued = 'sanction_letter_issued';
    case Disbursed = 'disbursed';
    case Withdrawn = 'withdrawn';

    public function getLabel(): string
    {
        return match ($this) {
            self::NotSubmitted => __('Not submitted'),
            self::Submitted => __('Submitted to lender'),
            self::UnderReview => __('Under lender review'),
            self::AdditionalDocumentsRequired => __('Lender needs more documents'),
            self::CreditAssessment => __('Lender credit assessment'),
            self::Approved => __('Approved by lender'),
            self::Rejected => __('Declined by lender'),
            self::SanctionLetterIssued => __('Sanction letter issued'),
            self::Disbursed => __('Disbursed by lender'),
            self::Withdrawn => __('Withdrawn'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::NotSubmitted => 'gray',
            self::AdditionalDocumentsRequired => 'warning',
            self::Approved, self::SanctionLetterIssued, self::Disbursed => 'success',
            self::Rejected, self::Withdrawn => 'danger',
            default => 'info',
        };
    }

    public function isSubmitted(): bool
    {
        return $this !== self::NotSubmitted;
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Rejected, self::Disbursed, self::Withdrawn], true);
    }

    /**
     * Approved, sanctioned or disbursed — what the dashboard counts as a
     * lender-confirmed approval.
     */
    public function isLenderApproved(): bool
    {
        return in_array($this, [self::Approved, self::SanctionLetterIssued, self::Disbursed], true);
    }
}
