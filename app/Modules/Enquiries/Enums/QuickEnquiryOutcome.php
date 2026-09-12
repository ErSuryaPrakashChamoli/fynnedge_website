<?php

namespace App\Modules\Enquiries\Enums;

/**
 * What SubmitQuickEnquiry did with a number. The public endpoint maps this to a
 * message and nothing else — the visitor never learns which record was touched.
 */
enum QuickEnquiryOutcome: string
{
    case Created = 'created';
    case Reopened = 'reopened';
    case Duplicate = 'duplicate';

    /**
     * Reopening an enquiry the team had already closed is a fresh request as
     * far as the visitor is concerned, so it gets the same thank-you as a
     * brand-new one.
     */
    public function isNewRequest(): bool
    {
        return $this !== self::Duplicate;
    }
}
