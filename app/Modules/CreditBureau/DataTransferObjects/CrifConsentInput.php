<?php

namespace App\Modules\CreditBureau\DataTransferObjects;

use App\Modules\CreditBureau\Enums\ConsentMethod;
use App\Modules\CreditBureau\Models\CreditConsent;
use Carbon\CarbonInterface;

/**
 * The consent behind a CRIF request: either a consent the customer already gave
 * on the website journey (an existing CreditConsent), or one an employee
 * records now with its method, evidence reference and an attestation. Whether
 * it is valid for this customer, PAN and date is checked by RequestCrifReport.
 */
final readonly class CrifConsentInput
{
    private function __construct(
        public ?CreditConsent $existing,
        public ?ConsentMethod $method,
        public ?string $evidenceReference,
        public ?CarbonInterface $consentedAt,
        public bool $attested,
    ) {}

    public static function existing(CreditConsent $consent): self
    {
        return new self($consent, null, null, null, true);
    }

    public static function recordedByEmployee(ConsentMethod $method, ?string $evidenceReference, ?CarbonInterface $consentedAt, bool $attested): self
    {
        return new self(null, $method, $evidenceReference !== null ? trim($evidenceReference) : null, $consentedAt, $attested);
    }
}
