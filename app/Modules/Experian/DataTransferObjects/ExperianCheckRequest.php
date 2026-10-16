<?php

namespace App\Modules\Experian\DataTransferObjects;

use App\Modules\CreditBureau\DataTransferObjects\CreditConsentInput;
use App\Modules\Customers\Models\Customer;
use App\Modules\Experian\Enums\ExperianProduct;
use App\Modules\Experian\Enums\ExperianRequestType;
use App\Modules\Experian\Exceptions\ExperianRequestRefused;
use App\Modules\Experian\Models\ExperianCreditRequest;
use App\Modules\Journey\Models\JourneySession;

/**
 * What a caller asked for — from the admin, the internal API or another module
 * through ExperianCreditService. Values arrive as typed; RequestExperianCheck
 * normalises and validates them before anything is stored or sent.
 */
final readonly class ExperianCheckRequest
{
    public function __construct(
        public Customer $customer,
        public ExperianProduct $product,
        public string $name,
        public string $mobile,
        public string $pan,
        public CreditConsentInput $consent,
        public ExperianRequestType $type = ExperianRequestType::Standard,
        public ?string $freshReason = null,
        public ?JourneySession $journeySession = null,
        public ?ExperianCreditRequest $previousRequest = null,
        public string $channel = 'admin',
    ) {}

    /**
     * A PDF of the same person as an earlier score or report request, on the
     * consent recorded for it (still checked for validity by RequestExperianCheck).
     */
    public static function pdfFor(ExperianCreditRequest $request, string $channel = 'admin'): self
    {
        if ($request->customer === null || $request->consent === null || $request->purged_at !== null) {
            throw new ExperianRequestRefused('A PDF can no longer be requested from this record: its customer, consent or details are no longer on record. Start a new request from the customer instead.');
        }

        return new self(
            customer: $request->customer,
            product: ExperianProduct::Pdf,
            name: (string) $request->full_name,
            mobile: (string) $request->mobile,
            pan: (string) $request->pan_encrypted,
            consent: CreditConsentInput::existing($request->consent),
            journeySession: $request->journeySession,
            channel: $channel,
        );
    }

    /**
     * A retry of a failed attempt: same person, same details, same consent.
     */
    public static function retryOf(ExperianCreditRequest $request, string $channel = 'admin'): self
    {
        if ($request->customer === null || $request->consent === null || $request->purged_at !== null) {
            throw new ExperianRequestRefused('This attempt can no longer be retried: its customer, consent or details are no longer on record. Start a new request from the customer instead.');
        }

        return new self(
            customer: $request->customer,
            product: $request->product,
            name: (string) $request->full_name,
            mobile: (string) $request->mobile,
            pan: (string) $request->pan_encrypted,
            consent: CreditConsentInput::existing($request->consent),
            type: ExperianRequestType::Retry,
            journeySession: $request->journeySession,
            previousRequest: $request,
            channel: $channel,
        );
    }
}
