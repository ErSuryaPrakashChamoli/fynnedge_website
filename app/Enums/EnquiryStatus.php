<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The lifecycle of an enquiry, from the form to a decision. Converted and
 * Rejected are what make conversion reporting per loan product possible —
 * "enquiries → converted → %" needs an outcome, not just "closed".
 */
enum EnquiryStatus: string implements HasColor, HasLabel
{
    case New = 'new';
    case Contacted = 'contacted';
    case FollowUp = 'follow_up';
    case Converted = 'converted';
    case Rejected = 'rejected';
    case Closed = 'closed';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Contacted => 'Contacted',
            self::FollowUp => 'Follow Up',
            self::Converted => 'Converted',
            self::Rejected => 'Rejected',
            self::Closed => 'Closed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'warning',
            self::Contacted => 'info',
            self::FollowUp => 'primary',
            self::Converted => 'success',
            self::Rejected => 'danger',
            self::Closed => 'gray',
        };
    }

    /**
     * Whether the team is still working this enquiry. A second enquiry about the
     * same product while one of these is open is a nudge, not a new lead — see
     * App\Modules\Enquiries\Actions\RecordEnquiry.
     */
    public function isOpen(): bool
    {
        return match ($this) {
            self::New, self::Contacted, self::FollowUp => true,
            self::Converted, self::Rejected, self::Closed => false,
        };
    }

    /**
     * The statuses that close an enquiry out. Stamping handled_at on any of
     * these keeps the pre-existing "Handled" column honest.
     */
    public function isSettled(): bool
    {
        return ! $this->isOpen();
    }
}
