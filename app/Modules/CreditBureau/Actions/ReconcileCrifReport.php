<?php

namespace App\Modules\CreditBureau\Actions;

use App\Models\User;
use App\Modules\CreditBureau\Enums\CrifReconciliationResolution;
use App\Modules\CreditBureau\Enums\CrifReportStatus;
use App\Modules\CreditBureau\Exceptions\CrifRequestRefused;
use App\Modules\CreditBureau\Models\CrifReport;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Closes an attempt whose outcome was uncertain (timeout, gateway error,
 * success without a report…) on the strength of what an admin established
 * from the Surepass dashboard or support — there is no provider API to ask.
 *
 *  - NotProcessed: nothing was charged, so the attempt becomes a retryable failure.
 *  - ProcessedCharged: Surepass did the (paid) work but we hold no report; the
 *    attempt becomes a non-retryable failure, so pulling again is a deliberate
 *    decision (a new standard request, or an authorised fresh one).
 *
 * Either way the PAN's in-flight lock is released and the note is audited.
 */
class ReconcileCrifReport
{
    public function handle(CrifReport $report, CrifReconciliationResolution $resolution, string $note, User $actor): CrifReport
    {
        if (! $actor->can('reconcile', $report)) {
            throw new AuthorizationException('You are not allowed to reconcile CRIF requests.');
        }

        $note = trim($note);

        if (mb_strlen($note) < 10) {
            throw new CrifRequestRefused('Explain how the outcome was confirmed (at least 10 characters), e.g. "Checked Surepass usage log 15 Oct: no charge for this call".');
        }

        return DB::transaction(function () use ($report, $resolution, $note, $actor): CrifReport {
            $locked = CrifReport::query()->whereKey($report->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== CrifReportStatus::ReconciliationRequired) {
                throw new CrifRequestRefused('Only a request awaiting reconciliation can be reconciled.');
            }

            $locked->update([
                'status' => CrifReportStatus::Failed,
                'in_flight_key' => null,
                'retryable' => $resolution === CrifReconciliationResolution::NotProcessed,
                'reconciliation_resolution' => $resolution,
                'reconciliation_note' => mb_substr($note, 0, 2000),
                'reconciled_by' => $actor->id,
                'reconciled_at' => now(),
            ]);

            return $locked;
        });
    }
}
