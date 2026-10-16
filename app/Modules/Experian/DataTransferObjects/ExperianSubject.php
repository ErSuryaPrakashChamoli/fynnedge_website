<?php

namespace App\Modules\Experian\DataTransferObjects;

use App\Modules\CreditBureau\Enums\ConsentStatus;
use App\Modules\CreditBureau\Models\CreditConsent;
use InvalidArgumentException;

/**
 * Who an Experian request is about, in the shape Surepass's
 * credit-report-experian API takes: `name`, `pan`, `mobile` and `consent`
 * (no gender, no id_type — checked against the live API's input validation on
 * 16 Oct 2026). It cannot be built without a CreditConsent in the Given state,
 * so `"consent": "Y"` is only ever sent on the strength of a recorded consent —
 * never merely because a customer exists. (Surepass does not validate the
 * consent field itself, which makes this check ours to enforce.)
 */
final readonly class ExperianSubject
{
    public function __construct(
        public string $name,
        public string $pan,
        public string $mobile,
        public CreditConsent $consent,
    ) {
        if ($consent->status !== ConsentStatus::Given) {
            throw new InvalidArgumentException('An Experian request needs a consent in the Given state.');
        }
    }

    /**
     * @return array{name: string, pan: string, mobile: string, consent: 'Y'}
     */
    public function toPayload(): array
    {
        return [
            'name' => $this->name,
            'pan' => $this->pan,
            'mobile' => $this->mobile,
            'consent' => 'Y',
        ];
    }
}
