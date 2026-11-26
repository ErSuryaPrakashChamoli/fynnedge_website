<?php

namespace App\Modules\DebtConsolidation\Actions;

use App\Enums\EnquiryType;
use App\Models\ContactEnquiry;
use App\Modules\DebtConsolidation\Enums\ActorType;
use App\Modules\DebtConsolidation\Enums\ApplicantType;
use App\Modules\DebtConsolidation\Enums\ConsentPurpose;
use App\Modules\DebtConsolidation\Enums\DebtApplicationStatus;
use App\Modules\DebtConsolidation\Models\DebtApplication;
use App\Modules\DebtConsolidation\Notifications\DebtCaseNotifier;
use App\Modules\DebtConsolidation\Services\DebtConsolidationSettings;
use App\Modules\Enquiries\Actions\RecordEnquiry;
use App\Modules\Enquiries\DataTransferObjects\EnquiryDraft;
use Illuminate\Support\Facades\DB;

/**
 * The short enquiry: an OTP-verified number with a few approximate figures.
 * It goes through the site's one enquiry path (RecordEnquiry → Lead Inbox,
 * Lead Analytics, FynnOn LMS) and opens — or updates — the customer's case
 * at "Enquiry received", which the detailed assessment later continues.
 */
class SubmitDebtEnquiry
{
    public function __construct(
        private readonly OpenDebtApplication $open,
        private readonly RecordEnquiry $enquiries,
        private readonly RecordDebtConsent $consents,
        private readonly DebtCaseNotifier $notifier,
    ) {}

    /**
     * @param  array{full_name: string, email?: ?string, city?: ?string, declared_total_outstanding?: ?float, declared_monthly_emi?: ?float, source_url?: ?string, utm_source?: ?string, utm_medium?: ?string, utm_campaign?: ?string}  $details
     */
    public function handle(string $verifiedMobile, ApplicantType $applicantType, array $details, ?string $ipAddress = null, ?string $userAgent = null): DebtApplication
    {
        DebtConsolidationSettings::ensureAccepting($applicantType);

        $application = $this->open->handle($verifiedMobile, $applicantType, $details);

        $this->enquiries->handle(new EnquiryDraft(
            phone: $verifiedMobile,
            type: EnquiryType::DebtConsolidation,
            enquirySource: $applicantType === ApplicantType::Business ? 'Business Debt Consolidation' : 'Personal Debt Consolidation',
            name: $details['full_name'],
            email: $details['email'] ?? null,
            loanAmount: $details['declared_total_outstanding'] ?? null,
            landingPage: $details['source_url'] ?? null,
            phoneVerified: true,
        ));

        DB::transaction(function () use ($application, $details, $verifiedMobile, $ipAddress, $userAgent): void {
            $enquiry = ContactEnquiry::query()->forPhone($verifiedMobile)->where('enquiry_type', EnquiryType::DebtConsolidation)->first();
            $wasDraft = $application->status === DebtApplicationStatus::Draft;

            $application->forceFill([
                'contact_enquiry_id' => $application->contact_enquiry_id ?? $enquiry?->getKey(),
                'declared_total_outstanding' => $details['declared_total_outstanding'] ?? $application->declared_total_outstanding,
                'declared_monthly_emi' => $details['declared_monthly_emi'] ?? $application->declared_monthly_emi,
                'enquiry_submitted_at' => $application->enquiry_submitted_at ?? now(),
                'status' => $wasDraft ? DebtApplicationStatus::EnquiryReceived : $application->status,
            ])->save();

            if ($wasDraft) {
                $application->recordStatusChange(DebtApplicationStatus::Draft->value, DebtApplicationStatus::EnquiryReceived->value, ActorType::Customer);
            }

            $this->consents->handle($application, ConsentPurpose::Contact, ipAddress: $ipAddress, userAgent: $userAgent);
        });

        $this->notifier->enquiryReceived($application);

        return $application;
    }
}
