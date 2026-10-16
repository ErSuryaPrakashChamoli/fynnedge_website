<?php

namespace App\Modules\Equifax\Actions;

use App\Models\User;
use App\Modules\Equifax\Enums\EquifaxReconciliationResolution;
use App\Modules\Equifax\Enums\EquifaxRequestStatus;
use App\Modules\Equifax\Exceptions\EquifaxRequestRefused;
use App\Modules\Equifax\Models\EquifaxCreditRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Closes an Equifax attempt whose outcome was uncertain (timeout, gateway
 * error, success without a result…) on the strength of what an admin
 * established from the Surepass dashboard or support — there is no provider
 * API to ask. NotProcessed makes it a retryable failure; ProcessedCharged a
 * non-retryable one. Either way the PAN + product lock is released and the
 * note is audited.
 */
class ReconcileEquifaxRequest
{
    public function handle(EquifaxCreditRequest $request, EquifaxReconciliationResolution $resolution, string $note, User $actor): EquifaxCreditRequest
    {
        if (! $actor->can('reconcile', $request)) {
            throw new AuthorizationException('You are not allowed to reconcile Equifax requests.');
        }

        $note = trim($note);

        if (mb_strlen($note) < 10) {
            throw new EquifaxRequestRefused('Explain how the outcome was confirmed (at least 10 characters), e.g. "Checked Surepass usage log 16 Oct: no charge for this call".');
        }

        return DB::transaction(function () use ($request, $resolution, $note, $actor): EquifaxCreditRequest {
            $locked = EquifaxCreditRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== EquifaxRequestStatus::ReconciliationRequired) {
                throw new EquifaxRequestRefused('Only a request awaiting reconciliation can be reconciled.');
            }

            $locked->update([
                'status' => EquifaxRequestStatus::Failed,
                'in_flight_key' => null,
                'retryable' => $resolution === EquifaxReconciliationResolution::NotProcessed,
                'reconciliation_resolution' => $resolution,
                'reconciliation_note' => mb_substr($note, 0, 2000),
                'reconciled_by' => $actor->id,
                'reconciled_at' => now(),
            ]);

            return $locked;
        });
    }
}
