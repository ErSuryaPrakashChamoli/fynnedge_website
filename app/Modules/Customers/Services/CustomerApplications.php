<?php

namespace App\Modules\Customers\Services;

use App\Models\LoanProduct;
use App\Modules\Applications\Enums\ApplicationStatus;
use App\Modules\Applications\Models\Application;
use App\Modules\Applications\Models\ApplicationDocument;
use App\Modules\Customers\Models\Customer;
use App\Modules\Journey\Enums\JourneyDefinitionStatus;
use App\Modules\Journey\Enums\JourneySessionStatus;
use App\Modules\Journey\Models\JourneyDefinition;
use App\Modules\Journey\Models\JourneySession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * A customer's own loan journeys, applications and documents, read through
 * the existing journey / application tables. Ownership is the verified-mobile
 * rule (JourneySession::verifiedFor()): what the customer started from the
 * portal, plus anything they applied for on the public site with the same
 * OTP-verified number.
 */
class CustomerApplications
{
    /** Statuses after which nothing more happens to an application. */
    public const CLOSED_STATUSES = [
        ApplicationStatus::Disbursed,
        ApplicationStatus::Rejected,
        ApplicationStatus::Withdrawn,
        ApplicationStatus::Cancelled,
    ];

    /**
     * Unfinished journeys (answers still being given), newest activity first.
     *
     * @return Collection<int, JourneySession>
     */
    public function drafts(Customer $customer): Collection
    {
        return JourneySession::query()
            ->verifiedFor($customer)
            ->where('status', JourneySessionStatus::InProgress)
            ->with(['loanProduct', 'currentStep'])
            ->latest('updated_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Finished journeys whose eligibility results are waiting for a lender choice.
     *
     * @return Collection<int, JourneySession>
     */
    public function awaitingLenderChoice(Customer $customer): Collection
    {
        return JourneySession::query()
            ->verifiedFor($customer)
            ->where('status', JourneySessionStatus::Completed)
            ->whereDoesntHave('eligibilityResults', fn (Builder $query) => $query->whereIn('id', Application::query()->select('eligibility_result_id')->whereNotNull('eligibility_result_id')))
            ->with('loanProduct')
            ->latest('completed_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return Builder<Application>
     */
    public function applications(Customer $customer): Builder
    {
        return Application::query()
            ->whereHas('journeySession', fn (Builder $query) => $query->verifiedFor($customer))
            ->with(['lenderProduct.lender', 'lenderProduct.loanProduct', 'documents'])
            ->latest('updated_at')
            ->orderByDesc('id');
    }

    public function activeCount(Customer $customer): int
    {
        return $this->applications($customer)->whereNotIn('status', self::CLOSED_STATUSES)->count()
            + $this->drafts($customer)->count();
    }

    /**
     * Required document slots still empty, per application id.
     *
     * @param  iterable<Application>  $applications
     * @return array<int, int>
     */
    public function missingDocumentCounts(iterable $applications): array
    {
        $counts = [];

        foreach ($applications as $application) {
            $uploaded = $application->documents->countBy('document_type_id');

            $counts[$application->id] = $application->requiredDocuments()
                ->sum(fn ($requirement): int => max(0, max($requirement->min_slots, 1) - $uploaded->get($requirement->document_type_id, 0)));
        }

        return $counts;
    }

    /**
     * @return Builder<ApplicationDocument>
     */
    public function documents(Customer $customer): Builder
    {
        return ApplicationDocument::query()
            ->whereHas('application.journeySession', fn (Builder $query) => $query->verifiedFor($customer))
            ->with(['documentType', 'application.lenderProduct.loanProduct'])
            ->latest('uploaded_at')
            ->orderByDesc('id');
    }

    /**
     * The loan products a customer can apply for right now: published, with a
     * live application journey. Nothing else is offered.
     *
     * @return Collection<int, LoanProduct>
     */
    public function applicableProducts(): Collection
    {
        return LoanProduct::query()
            ->published()
            ->whereIn('id', JourneyDefinition::query()->where('status', JourneyDefinitionStatus::Active)->select('loan_product_id'))
            ->orderedForDisplay()
            ->get();
    }
}
