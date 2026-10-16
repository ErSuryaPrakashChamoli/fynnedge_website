<?php

namespace App\Modules\Cibil\DataTransferObjects;

use App\Modules\Cibil\Enums\Gender;
use App\Modules\CreditBureau\Enums\ConsentStatus;
use App\Modules\CreditBureau\Models\CreditConsent;
use InvalidArgumentException;

/**
 * Who a CIBIL request is about, in the shape Surepass's credit-report-cibil
 * API takes: `mobile`, `pan`, `name`, `gender` and `consent` (gender is
 * required; checked against the live API's input validation on 16 Oct 2026).
 * It cannot be built without a CreditConsent in the Given state, so
 * `"consent": "Y"` is only ever sent on the strength of a recorded consent —
 * never merely because a customer exists.
 */
final readonly class CibilSubject
{
    public function __construct(
        public string $name,
        public string $pan,
        public string $mobile,
        public Gender $gender,
        public CreditConsent $consent,
    ) {
        if ($consent->status !== ConsentStatus::Given) {
            throw new InvalidArgumentException('A CIBIL request needs a consent in the Given state.');
        }
    }

    /**
     * @return array{mobile: string, pan: string, name: string, gender: string, consent: 'Y'}
     */
    public function toPayload(): array
    {
        return [
            'mobile' => $this->mobile,
            'pan' => $this->pan,
            'name' => $this->name,
            'gender' => $this->gender->value,
            'consent' => 'Y',
        ];
    }
}
