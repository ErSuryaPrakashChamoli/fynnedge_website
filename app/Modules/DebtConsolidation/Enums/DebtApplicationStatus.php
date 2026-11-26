<?php

namespace App\Modules\DebtConsolidation\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * FynnEdge's own handling of a case. It is independent of each lender
 * application's status (LenderApplicationStatus) and of each liability's
 * closure status (ClosureStatus): FynnEdge facilitates, lenders decide.
 */
enum DebtApplicationStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case EnquiryReceived = 'enquiry_received';
    case Contacted = 'contacted';
    case InformationPending = 'information_pending';
    case AssessmentInProgress = 'assessment_in_progress';
    case AssessmentCompleted = 'assessment_completed';
    case LenderMatching = 'lender_matching';
    case CustomerConfirmation = 'customer_confirmation';
    case ReadyForSubmission = 'ready_for_submission';
    case LenderProcessing = 'lender_processing';
    case ClosureInProgress = 'closure_in_progress';
    case Completed = 'completed';
    case NotProceeding = 'not_proceeding';
    case Withdrawn = 'withdrawn';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => __('Not yet submitted'),
            self::EnquiryReceived => __('Enquiry received'),
            self::Contacted => __('Contacted'),
            self::InformationPending => __('Information pending'),
            self::AssessmentInProgress => __('Assessment in progress'),
            self::AssessmentCompleted => __('Assessment completed'),
            self::LenderMatching => __('Lender matching'),
            self::CustomerConfirmation => __('Awaiting your confirmation'),
            self::ReadyForSubmission => __('Ready for lender submission'),
            self::LenderProcessing => __('With lender'),
            self::ClosureInProgress => __('Closing existing loans'),
            self::Completed => __('Completed'),
            self::NotProceeding => __('Not proceeding'),
            self::Withdrawn => __('Withdrawn'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::EnquiryReceived, self::Contacted => 'info',
            self::InformationPending, self::CustomerConfirmation => 'warning',
            self::Completed => 'success',
            self::NotProceeding, self::Withdrawn => 'danger',
            default => 'primary',
        };
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Completed, self::NotProceeding, self::Withdrawn], true);
    }

    /**
     * Statuses staff may set by hand. Draft belongs to the customer's
     * unfinished form; Completed is reached only through closure verification.
     *
     * @return list<self>
     */
    public static function staffSelectable(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status): bool => ! in_array($status, [self::Draft, self::Completed], true)));
    }
}
