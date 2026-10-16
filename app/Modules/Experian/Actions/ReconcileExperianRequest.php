<?php

namespace App\Modules\Experian\Actions;

use App\Models\User;
use App\Modules\Experian\Enums\ExperianReconciliationResolution;
use App\Modules\Experian\Enums\ExperianRequestStatus;
use App\Modules\Experian\Exceptions\ExperianRequestRefused;
use App\Modules\Experian\Models\ExperianCreditRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Closes an Experian attempt whose outcome was uncertain (timeout, gateway
 * error, success without a result…) on the strength of what an admin
 * established from the Surepass dashboard or support — there is no provider
 * API to ask. NotProcessed makes it a retryable failure; ProcessedCharged a
 * non-retryable one. Either way the PAN + product lock is released and the
 * note is audited.
 */
class ReconcileExperianRequest
{
    public function handle(ExperianCreditRequest $request, ExperianReconciliationResolution $resolution, string $note, User $actor): ExperianCreditRequest
    {
        if (! $actor->can('reconcile', $request)) {
            throw new AuthorizationException('You are not allowed to reconcile Experian requests.');
        }

        $note = trim($note);

        if (mb_strlen($note) < 10) {
            throw new ExperianRequestRefused('Explain how the outcome was confirmed (at least 10 characters), e.g. "Checked Surepass usage log 16 Oct: no charge for this call".');
        }

        return DB::transaction(function () use ($request, $resolution, $note, $actor): ExperianCreditRequest {
            $locked = ExperianCreditRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== ExperianRequestStatus::ReconciliationRequired) {
                throw new ExperianRequestRefused('Only a request awaiting reconciliation can be reconciled.');
            }

            $locked->update([
                'status' => ExperianRequestStatus::Failed,
                'in_flight_key' => null,
                'retryable' => $resolution === ExperianReconciliationResolution::NotProcessed,
                'reconciliation_resolution' => $resolution,
                'reconciliation_note' => mb_substr($note, 0, 2000),
                'reconciled_by' => $actor->id,
                'reconciled_at' => now(),
            ]);

            return $locked;
        });
    }
}
