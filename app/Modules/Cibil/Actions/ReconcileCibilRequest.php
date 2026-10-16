<?php

namespace App\Modules\Cibil\Actions;

use App\Models\User;
use App\Modules\Cibil\Enums\CibilReconciliationResolution;
use App\Modules\Cibil\Enums\CibilRequestStatus;
use App\Modules\Cibil\Exceptions\CibilRequestRefused;
use App\Modules\Cibil\Models\CibilCreditRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Closes a CIBIL attempt whose outcome was uncertain (timeout, gateway
 * error, success without a result…) on the strength of what an admin
 * established from the Surepass dashboard or support — there is no provider
 * API to ask. NotProcessed makes it a retryable failure; ProcessedCharged a
 * non-retryable one. Either way the PAN + product lock is released and the
 * note is audited.
 */
class ReconcileCibilRequest
{
    public function handle(CibilCreditRequest $request, CibilReconciliationResolution $resolution, string $note, User $actor): CibilCreditRequest
    {
        if (! $actor->can('reconcile', $request)) {
            throw new AuthorizationException('You are not allowed to reconcile CIBIL requests.');
        }

        $note = trim($note);

        if (mb_strlen($note) < 10) {
            throw new CibilRequestRefused('Explain how the outcome was confirmed (at least 10 characters), e.g. "Checked Surepass usage log 16 Oct: no charge for this call".');
        }

        return DB::transaction(function () use ($request, $resolution, $note, $actor): CibilCreditRequest {
            $locked = CibilCreditRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== CibilRequestStatus::ReconciliationRequired) {
                throw new CibilRequestRefused('Only a request awaiting reconciliation can be reconciled.');
            }

            $locked->update([
                'status' => CibilRequestStatus::Failed,
                'in_flight_key' => null,
                'retryable' => $resolution === CibilReconciliationResolution::NotProcessed,
                'reconciliation_resolution' => $resolution,
                'reconciliation_note' => mb_substr($note, 0, 2000),
                'reconciled_by' => $actor->id,
                'reconciled_at' => now(),
            ]);

            return $locked;
        });
    }
}
