<?php

namespace App\Modules\Equifax\DataTransferObjects;

use App\Modules\CreditBureau\Enums\ConsentStatus;
use App\Modules\CreditBureau\Models\CreditConsent;
use App\Modules\Equifax\Enums\Gender;
use InvalidArgumentException;

/**
 * Who an Equifax request is about, in the shape Surepass's credit-report-v2
 * API takes: one `name`, the PAN as `id_number` with `id_type: "pan"`, the
 * mobile and the gender. It cannot be built without a CreditConsent in the
 * Given state, so `"consent": "Y"` is only ever sent on the strength of a
 * recorded consent — never merely because a customer exists.
 */
final readonly class EquifaxSubject
{
    public function __construct(
        public string $name,
        public string $pan,
        public string $mobile,
        public Gender $gender,
        public CreditConsent $consent,
    ) {
        if ($consent->status !== ConsentStatus::Given) {
            throw new InvalidArgumentException('An Equifax request needs a consent in the Given state.');
        }
    }

    /**
     * @return array{name: string, id_number: string, id_type: 'pan', mobile: string, consent: 'Y', gender: string}
     */
    public function toPayload(): array
    {
        return [
            'name' => $this->name,
            'id_number' => $this->pan,
            'id_type' => 'pan',
            'mobile' => $this->mobile,
            'consent' => 'Y',
            'gender' => $this->gender->value,
        ];
    }
}
