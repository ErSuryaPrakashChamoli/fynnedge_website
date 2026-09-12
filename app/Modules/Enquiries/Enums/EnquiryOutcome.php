<?php

namespace App\Modules\Enquiries\Enums;

/**
 * What RecordEnquiry did with a submission. The public endpoints map this to a
 * message and nothing else — the visitor never learns which record was touched.
 */
enum EnquiryOutcome: string
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
