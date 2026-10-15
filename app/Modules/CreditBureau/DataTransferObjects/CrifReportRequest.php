<?php

namespace App\Modules\CreditBureau\DataTransferObjects;

use App\Modules\CreditBureau\Enums\CrifRequestType;
use App\Modules\CreditBureau\Exceptions\CrifRequestRefused;
use App\Modules\CreditBureau\Models\CrifReport;
use App\Modules\Customers\Models\Customer;
use App\Modules\Journey\Models\JourneySession;

/**
 * What an employee asked for. Values arrive as typed; RequestCrifReport
 * normalises and validates them before anything is stored or sent.
 */
final readonly class CrifReportRequest
{
    public function __construct(
        public Customer $customer,
        public string $firstName,
        public string $lastName,
        public string $mobile,
        public string $pan,
        public CrifConsentInput $consent,
        public CrifRequestType $type = CrifRequestType::Standard,
        public ?string $freshReason = null,
        public ?JourneySession $journeySession = null,
        public ?CrifReport $previousReport = null,
    ) {}

    /**
     * A retry of a failed attempt: same person, same details, same consent.
     */
    public static function retryOf(CrifReport $report): self
    {
        if ($report->customer === null || $report->consent === null || $report->purged_at !== null) {
            throw new CrifRequestRefused('This attempt can no longer be retried: its customer, consent or details are no longer on record. Start a new request from the customer instead.');
        }

        return new self(
            customer: $report->customer,
            firstName: (string) $report->first_name,
            lastName: (string) $report->last_name,
            mobile: (string) $report->mobile,
            pan: (string) $report->pan_encrypted,
            consent: CrifConsentInput::existing($report->consent),
            type: CrifRequestType::Retry,
            journeySession: $report->journeySession,
            previousReport: $report,
        );
    }
}
