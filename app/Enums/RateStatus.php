<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Where a lender rate record is in the publication workflow. Only Published
 * records can ever reach the public site, and only while their effective and
 * promotion dates allow it — "expired" is derived from those dates (see
 * LenderProductRate::displayStatus()), never stored, so an offer stops
 * showing the moment it lapses without anything having to run.
 */
enum RateStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Published = 'published';
    case Archived = 'archived';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingApproval => 'Awaiting approval',
            self::Published => 'Published',
            self::Archived => 'Archived',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::PendingApproval => 'warning',
            self::Published => 'success',
            self::Archived => 'gray',
        };
    }

    /**
     * Drafts are the only editable state: anything that has been submitted,
     * published or archived is evidence of what was (or was about to be)
     * shown to customers, so changing it means creating a new version.
     */
    public function isEditable(): bool
    {
        return $this === self::Draft;
    }
}
