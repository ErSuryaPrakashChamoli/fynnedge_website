<?php

namespace App\Modules\Enquiries\DataTransferObjects;

use App\Enums\EnquiryType;

/**
 * One submitted enquiry, already validated and normalised, on its way to
 * RecordEnquiry. Every field here is resolved server-side — in particular
 * `loanProductId` and `enquirySource`, which a visitor must never be able to
 * choose, or the reporting they feed becomes whatever a form tamperer says.
 */
class EnquiryDraft
{
    public function __construct(
        public readonly string $phone,
        public readonly EnquiryType $type,
        public readonly string $enquirySource,
        public readonly ?int $loanProductId = null,
        public readonly ?string $name = null,
        public readonly ?string $email = null,
        public readonly ?float $loanAmount = null,
        public readonly ?string $landingPage = null,
        public readonly bool $phoneVerified = false,
        /** The channel, not the placement: "website" for every public form. */
        public readonly string $source = 'website',
    ) {}

    /**
     * The key a duplicate is judged against. An enquiry about a specific product
     * only collides with another about that same product — someone who asked
     * about a Personal Loan in March and a Home Loan in June has told us two
     * different things, and collapsing them would lose one of them.
     *
     * @return array{0: string, 1: int|null}
     */
    public function identity(): array
    {
        return [$this->phone, $this->loanProductId];
    }
}
