<?php

namespace App\Modules\DebtConsolidation\Actions;

use App\Enums\EnquiryType;
use App\Models\ContactEnquiry;
use App\Modules\DebtConsolidation\Enums\ActorType;
use App\Modules\DebtConsolidation\Enums\ApplicantType;
use App\Modules\DebtConsolidation\Enums\ConsentPurpose;
use App\Modules\DebtConsolidation\Enums\DebtApplicationStatus;
use App\Modules\DebtConsolidation\Models\DebtApplication;
use App\Modules\DebtConsolidation\Models\DebtCalculationSnapshot;
use App\Modules\DebtConsolidation\Notifications\DebtCaseNotifier;
use App\Modules\DebtConsolidation\Services\DebtConsolidationSettings;
use App\Modules\Enquiries\Actions\RecordEnquiry;
use App\Modules\Enquiries\DataTransferObjects\EnquiryDraft;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The customer's "Submit": records the assessment and contact consents with
 * their exact wording, stamps the case submitted, stores the assessment
 * snapshot the customer reviewed, makes sure the lead is in the enquiry
 * pipeline, and tells the customer and the team. Resubmitting after staff
 * asked for more information returns the case to "Assessment in progress"
 * and tells the advisor.
 */
class SubmitDebtApplication
{
    public function __construct(
        private readonly RecordDebtConsent $consents,
        private readonly AssessDebtApplication $assess,
        private readonly RecordEnquiry $enquiries,
        private readonly DebtCaseNotifier $notifier,
    ) {}

    public function handle(DebtApplication $application, ?string $ipAddress = null, ?string $userAgent = null): DebtCalculationSnapshot
    {
        if (! $application->isEditableByCustomer()) {
            throw ValidationException::withMessages(['application' => __('This application has already been submitted.')]);
        }

        DebtConsolidationSettings::ensureAccepting($application->applicant_type);

        if (! $application->liabilities()->exists()) {
            throw ValidationException::withMessages(['liabilities' => __('Add at least one loan or card before submitting.')]);
        }

        $this->ensureComplete($application);

        $firstSubmission = ! $application->isSubmitted();

        DB::transaction(function () use ($application, $ipAddress, $userAgent, $firstSubmission): void {
            foreach ([ConsentPurpose::Assessment, ConsentPurpose::Contact] as $purpose) {
                if (! $this->consents->hasActive($application, $purpose)) {
                    $this->consents->handle($application, $purpose, ipAddress: $ipAddress, userAgent: $userAgent);
                }
            }

            $from = $application->status;
            $to = match ($from) {
                DebtApplicationStatus::Draft, DebtApplicationStatus::EnquiryReceived => DebtApplicationStatus::EnquiryReceived,
                // The customer answered the advisor's request: the case goes back to them.
                DebtApplicationStatus::InformationPending => DebtApplicationStatus::AssessmentInProgress,
                default => $from,
            };

            $application->forceFill([
                'status' => $to,
                'submitted_at' => $application->submitted_at ?? now(),
                'enquiry_submitted_at' => $application->enquiry_submitted_at ?? now(),
                'journey_step' => 'submitted',
                'last_customer_activity_at' => now(),
            ])->save();

            if ($from !== $to) {
                $application->recordStatusChange($from->value, $to->value, ActorType::Customer);
            }

            $application->recordEvent($firstSubmission ? 'customer_submitted' : 'customer_resubmitted');
        });

        $snapshot = $this->assess->handle($application, ActorType::Customer);

        if ($application->contact_enquiry_id === null) {
            $this->enquiries->handle(new EnquiryDraft(
                phone: $application->mobile,
                type: EnquiryType::DebtConsolidation,
                enquirySource: $application->applicant_type === ApplicantType::Business ? 'Business Debt Consolidation' : 'Personal Debt Consolidation',
                name: $application->full_name,
                email: $application->email,
                loanAmount: $snapshot->total_outstanding === null ? null : (float) $snapshot->total_outstanding,
                landingPage: $application->source_url,
                phoneVerified: true,
            ));

            $application->forceFill([
                'contact_enquiry_id' => ContactEnquiry::query()->forPhone($application->mobile)->where('enquiry_type', EnquiryType::DebtConsolidation)->value('id'),
            ])->save();
        }

        if ($firstSubmission) {
            $this->notifier->applicationSubmitted($application);
        } else {
            $this->notifier->applicationResubmitted($application);
        }

        return $snapshot;
    }

    /**
     * The minimum the assessment needs, checked here as well as on each form
     * step, so skipping steps cannot submit an empty case.
     *
     * @throws ValidationException
     */
    private function ensureComplete(DebtApplication $application): void
    {
        $profile = $application->financialProfile()->first();

        $missing = $application->applicant_type === ApplicantType::Business
            ? ($profile === null || (float) $profile->average_monthly_revenue <= 0 || $profile->monthly_operating_expenses === null || blank($profile->business_name) || $profile->business_constitution === null || $profile->business_vintage_months === null)
            : ($profile === null || (float) $profile->net_monthly_income <= 0 || $profile->employment_type === null || $profile->date_of_birth === null);

        if ($missing) {
            throw ValidationException::withMessages(['application' => __('Some required details are missing. Please complete the income and profile steps before submitting.')]);
        }
    }
}
