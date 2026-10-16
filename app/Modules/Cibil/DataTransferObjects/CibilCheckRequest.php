<?php

namespace App\Modules\Cibil\DataTransferObjects;

use App\Modules\Cibil\Enums\CibilProduct;
use App\Modules\Cibil\Enums\CibilRequestType;
use App\Modules\Cibil\Enums\Gender;
use App\Modules\Cibil\Exceptions\CibilRequestRefused;
use App\Modules\Cibil\Models\CibilCreditRequest;
use App\Modules\CreditBureau\DataTransferObjects\CreditConsentInput;
use App\Modules\Customers\Models\Customer;
use App\Modules\Journey\Models\JourneySession;

/**
 * What a caller asked for — from the admin, the internal API or another module
 * through CibilCreditService. Values arrive as typed; RequestCibilCheck
 * normalises and validates them before anything is stored or sent.
 */
final readonly class CibilCheckRequest
{
    public function __construct(
        public Customer $customer,
        public CibilProduct $product,
        public string $name,
        public string $mobile,
        public string $pan,
        public ?Gender $gender,
        public CreditConsentInput $consent,
        public CibilRequestType $type = CibilRequestType::Standard,
        public ?string $freshReason = null,
        public ?JourneySession $journeySession = null,
        public ?CibilCreditRequest $previousRequest = null,
        public string $channel = 'admin',
    ) {}

    /**
     * A PDF of the same person as an earlier score or report request, on the
     * consent recorded for it (still checked for validity by RequestCibilCheck).
     */
    public static function pdfFor(CibilCreditRequest $request, string $channel = 'admin'): self
    {
        if ($request->customer === null || $request->consent === null || $request->purged_at !== null) {
            throw new CibilRequestRefused('A PDF can no longer be requested from this record: its customer, consent or details are no longer on record. Start a new request from the customer instead.');
        }

        return new self(
            customer: $request->customer,
            product: CibilProduct::Pdf,
            name: (string) $request->full_name,
            mobile: (string) $request->mobile,
            pan: (string) $request->pan_encrypted,
            gender: $request->gender,
            consent: CreditConsentInput::existing($request->consent),
            journeySession: $request->journeySession,
            channel: $channel,
        );
    }

    /**
     * A retry of a failed attempt: same person, same details, same consent.
     */
    public static function retryOf(CibilCreditRequest $request, string $channel = 'admin'): self
    {
        if ($request->customer === null || $request->consent === null || $request->purged_at !== null) {
            throw new CibilRequestRefused('This attempt can no longer be retried: its customer, consent or details are no longer on record. Start a new request from the customer instead.');
        }

        return new self(
            customer: $request->customer,
            product: $request->product,
            name: (string) $request->full_name,
            mobile: (string) $request->mobile,
            pan: (string) $request->pan_encrypted,
            gender: $request->gender,
            consent: CreditConsentInput::existing($request->consent),
            type: CibilRequestType::Retry,
            journeySession: $request->journeySession,
            previousRequest: $request,
            channel: $channel,
        );
    }
}
