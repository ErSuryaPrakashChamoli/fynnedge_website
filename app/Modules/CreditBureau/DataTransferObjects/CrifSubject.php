<?php

namespace App\Modules\CreditBureau\DataTransferObjects;

use App\Modules\CreditBureau\Enums\ConsentStatus;
use App\Modules\CreditBureau\Models\CreditConsent;
use InvalidArgumentException;

/**
 * Who a CRIF request is about. It cannot be built without a CreditConsent in
 * the Given state, so `"consent": "Y"` is only ever sent on the strength of a
 * recorded consent — never merely because a customer exists. Whether that
 * consent is recent enough and belongs to this person is decided earlier, by
 * App\Modules\CreditBureau\Actions\RequestCrifReport.
 */
final readonly class CrifSubject
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $mobile,
        public string $pan,
        public CreditConsent $consent,
    ) {
        if ($consent->status !== ConsentStatus::Given) {
            throw new InvalidArgumentException('A CRIF request needs a consent in the Given state.');
        }
    }

    /**
     * @return array{first_name: string, last_name: string, mobile: string, pan: string, consent: 'Y'}
     */
    public function toPayload(): array
    {
        return [
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'mobile' => $this->mobile,
            'pan' => $this->pan,
            'consent' => 'Y',
        ];
    }
}
