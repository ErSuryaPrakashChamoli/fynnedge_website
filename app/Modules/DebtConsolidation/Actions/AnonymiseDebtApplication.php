<?php

namespace App\Modules\DebtConsolidation\Actions;

use App\Models\AuditLog;
use App\Modules\DebtConsolidation\Enums\ActorType;
use App\Modules\DebtConsolidation\Enums\DebtApplicationStatus;
use App\Modules\DebtConsolidation\Enums\LenderApplicationStatus;
use App\Modules\DebtConsolidation\Models\DebtApplication;
use App\Modules\DebtConsolidation\Models\DebtCalculationSnapshot;
use App\Modules\DebtConsolidation\Models\DebtCollateral;
use App\Modules\DebtConsolidation\Models\DebtDocument;
use App\Modules\DebtConsolidation\Models\DebtFinancialProfile;
use App\Modules\DebtConsolidation\Models\DebtForeclosureQuotation;
use App\Modules\DebtConsolidation\Models\DebtLenderApplication;
use App\Modules\DebtConsolidation\Models\DebtLenderMatch;
use App\Modules\DebtConsolidation\Models\DebtLiability;
use App\Modules\DebtConsolidation\Models\DebtNote;
use App\Modules\DebtConsolidation\Models\DebtReport;
use App\Modules\DebtConsolidation\Models\DebtScenario;
use App\Modules\DebtConsolidation\Models\DebtStatusChange;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Removes a case's personal data (Owner decision M3, 2026-11-26), for an
 * abandoned draft past its retention period or a customer's erasure request.
 *
 * Blanked: name, email, mobile, location, declared amounts, campaign data and
 * the customer link. Deleted: financial profile, loans and cards with their
 * quotations, collateral, documents and reports (with their encrypted files),
 * calculation snapshots, scenarios, lender matches, unsubmitted lender
 * applications and notes, and the audit rows of everything deleted. Kept as
 * evidence: the reference and dates, the consent records, and the status
 * history (its notes blanked).
 *
 * A case a lender has received (any lender application past "not
 * submitted") or that has closure tracking is refused: those records are
 * kept under the record-keeping period (OD8).
 *
 * Immutable records (snapshots, status history) are changed here by direct
 * queries on purpose: erasure is the one sanctioned exception to their
 * model guards.
 */
class AnonymiseDebtApplication
{
    public const REASON_RETENTION = 'retention';

    public const REASON_REQUEST = 'erasure_request';

    /**
     * @throws ValidationException
     */
    public function handle(DebtApplication $application, string $reason, ?int $userId = null, ?string $reference = null): DebtApplication
    {
        if ($application->isAnonymised()) {
            return $application;
        }

        $withLender = $application->lenderApplications()->where('status', '!=', LenderApplicationStatus::NotSubmitted)->exists();

        if ($withLender || $application->closures()->exists()) {
            throw ValidationException::withMessages(['application' => 'This case has gone to a lender or has closure tracking, so its records are kept under the record-keeping period. It cannot be anonymised.']);
        }

        $leadId = $application->contact_enquiry_id;

        DB::transaction(function () use ($application, $reason, $userId, $reference, $leadId): void {
            $id = $application->getKey();
            $liabilityIds = DebtLiability::query()->where('debt_application_id', $id)->pluck('id');
            $documents = DebtDocument::withTrashed()->where('debt_application_id', $id)->get(['id', 'disk', 'path']);
            $reports = DebtReport::query()->where('debt_application_id', $id)->get(['id', 'disk', 'path']);
            $lenderApplicationIds = DebtLenderApplication::query()->where('debt_application_id', $id)->pluck('id');

            $deleted = [
                DebtLenderMatch::class => DebtLenderMatch::query()->where('debt_application_id', $id)->pluck('id'),
                DebtLenderApplication::class => $lenderApplicationIds,
                DebtReport::class => $reports->pluck('id'),
                DebtScenario::class => DebtScenario::query()->where('debt_application_id', $id)->pluck('id'),
                DebtDocument::class => $documents->pluck('id'),
                DebtForeclosureQuotation::class => DebtForeclosureQuotation::query()->whereIn('debt_liability_id', $liabilityIds)->pluck('id'),
                DebtCollateral::class => DebtCollateral::query()->where('debt_application_id', $id)->pluck('id'),
                DebtLiability::class => $liabilityIds,
                DebtFinancialProfile::class => DebtFinancialProfile::query()->where('debt_application_id', $id)->pluck('id'),
                DebtCalculationSnapshot::class => DebtCalculationSnapshot::query()->where('debt_application_id', $id)->pluck('id'),
                DebtNote::class => DebtNote::query()->where('debt_application_id', $id)->pluck('id'),
            ];

            DebtStatusChange::query()->where('subject_type', (new DebtLenderApplication)->getMorphClass())->whereIn('subject_id', $lenderApplicationIds)->delete();

            foreach ($deleted as $class => $ids) {
                /** @var Model $model */
                $model = new $class;
                AuditLog::query()->where('auditable_type', $model->getMorphClass())->whereIn('auditable_id', $ids)->delete();

                $query = $model->newQueryWithoutScopes()->whereKey($ids);
                in_array(SoftDeletes::class, class_uses_recursive($class), true) ? $query->forceDelete() : $query->delete();
            }

            foreach ([...$documents, ...$reports] as $file) {
                Storage::disk($file->disk)->delete($file->path);
            }

            DebtStatusChange::query()->where('subject_type', $application->getMorphClass())->where('subject_id', $id)->update(['note' => null]);
            AuditLog::query()->where('auditable_type', $application->getMorphClass())->where('auditable_id', $id)->update(['changes' => null]);

            $from = $application->status;
            $application->forceFill([
                'customer_id' => null,
                'contact_enquiry_id' => null,
                'full_name' => null,
                'email' => null,
                'mobile' => DebtApplication::ANONYMISED_MOBILE,
                'city' => null,
                'state' => null,
                'pincode' => null,
                'declared_total_outstanding' => null,
                'declared_monthly_emi' => null,
                'source_url' => null,
                'utm_source' => null,
                'utm_medium' => null,
                'utm_campaign' => null,
                'follow_up_at' => null,
                'status' => $from->isOpen() ? DebtApplicationStatus::Withdrawn : $from,
                'closed_at' => $application->closed_at ?? now(),
                'closure_reason' => 'Personal data anonymised ('.($reason === self::REASON_RETENTION ? 'retention period' : 'erasure request').')',
            ])->saveQuietly();

            if ($from !== $application->status) {
                $application->recordStatusChange($from->value, $application->status->value, $userId === null ? ActorType::System : ActorType::Staff, $userId);
            }

            $application->recordEvent('personal_data_anonymised', array_filter([
                'reason' => $reason,
                'reference' => $reference,
                'lead_inbox_enquiry' => $leadId,
            ], fn (mixed $value): bool => $value !== null), $userId);
        });

        return $application;
    }
}
