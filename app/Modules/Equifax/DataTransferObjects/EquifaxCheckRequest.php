<?php

namespace App\Modules\Equifax\DataTransferObjects;

use App\Modules\CreditBureau\DataTransferObjects\CreditConsentInput;
use App\Modules\Customers\Models\Customer;
use App\Modules\Equifax\Enums\EquifaxProduct;
use App\Modules\Equifax\Enums\EquifaxRequestType;
use App\Modules\Equifax\Enums\Gender;
use App\Modules\Equifax\Exceptions\EquifaxRequestRefused;
use App\Modules\Equifax\Models\EquifaxCreditRequest;
use App\Modules\Journey\Models\JourneySession;

/**
 * What a caller asked for — from the admin, the internal API or another module
 * through EquifaxCreditService. Values arrive as typed; RequestEquifaxCheck
 * normalises and validates them before anything is stored or sent.
 */
final readonly class EquifaxCheckRequest
{
    public function __construct(
        public Customer $customer,
        public EquifaxProduct $product,
        public string $name,
        public string $mobile,
        public string $pan,
        public ?Gender $gender,
        public CreditConsentInput $consent,
        public EquifaxRequestType $type = EquifaxRequestType::Standard,
        public ?string $freshReason = null,
        public ?JourneySession $journeySession = null,
        public ?EquifaxCreditRequest $previousRequest = null,
        public string $channel = 'admin',
    ) {}

    /**
     * A retry of a failed attempt: same person, same details, same consent.
     */
    public static function retryOf(EquifaxCreditRequest $request, string $channel = 'admin'): self
    {
        if ($request->customer === null || $request->consent === null || $request->purged_at !== null) {
            throw new EquifaxRequestRefused('This attempt can no longer be retried: its customer, consent or details are no longer on record. Start a new request from the customer instead.');
        }

        return new self(
            customer: $request->customer,
            product: $request->product,
            name: (string) $request->full_name,
            mobile: (string) $request->mobile,
            pan: (string) $request->pan_encrypted,
            gender: $request->gender,
            consent: CreditConsentInput::existing($request->consent),
            type: EquifaxRequestType::Retry,
            journeySession: $request->journeySession,
            previousRequest: $request,
            channel: $channel,
        );
    }
}
