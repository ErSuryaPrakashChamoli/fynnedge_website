<?php

namespace App\Modules\DebtConsolidation\Actions;

use App\Modules\Applications\Enums\DocumentStatus;
use App\Modules\DebtConsolidation\Enums\ActorType;
use App\Modules\DebtConsolidation\Enums\ClosureStatus;
use App\Modules\DebtConsolidation\Enums\DebtApplicationStatus;
use App\Modules\DebtConsolidation\Models\DebtApplication;
use App\Modules\DebtConsolidation\Models\DebtClosure;
use App\Modules\DebtConsolidation\Models\DebtDocument;
use App\Modules\DebtConsolidation\Notifications\DebtCaseNotifier;
use App\Modules\DebtConsolidation\Services\MakerChecker;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moves the closure of one existing liability along, with the evidence each
 * step needs:
 *  - Quotation received: a foreclosure quotation is recorded for the loan.
 *  - Payment initiated: at least one payment is recorded.
 *  - Payment confirmed: confirmed payments cover the final closure amount.
 *  - NOC received: an NOC / closure letter document is attached (full closure).
 *  - Closure verified: payment confirmed AND, for a full closure, the NOC
 *    document has been verified by staff. A partial closure is verified on
 *    confirmed payment alone (there is no NOC for a part-payment).
 * The amount to cover is the final closure amount staff entered, else the
 * loan's foreclosure quotation; payment cannot be confirmed without one.
 * The NOC must be an NOC / closure letter for this loan. When every closure
 * of a case in "Closure in progress" is verified (or cancelled, with at
 * least one verified) the case is completed automatically.
 */
class UpdateClosureStatus
{
    public function __construct(private readonly DebtCaseNotifier $notifier) {}

    public function handle(DebtClosure $closure, ClosureStatus $to, int $userId, ?string $note = null, ?float $finalClosureAmount = null, ?DebtDocument $noc = null): DebtClosure
    {
        $from = $closure->status;

        if ($from === ClosureStatus::ClosureVerified && $to !== $from) {
            throw ValidationException::withMessages(['status' => 'A verified closure cannot be changed.']);
        }

        if ($to === ClosureStatus::ClosureVerified) {
            MakerChecker::ensureIndependent($closure, $userId, 'status', 'A different person must verify this closure: you recorded or changed it.');
        }

        if ($finalClosureAmount !== null) {
            $closure->final_closure_amount = $finalClosureAmount;
        }

        if ($noc !== null) {
            $this->guardNoc($closure, $noc);
            $closure->noc_document_id = $noc->getKey();
        }

        // A full closure is covered by the lender's foreclosure quotation, even one recorded
        // after the closure was opened. A part-payment has no quotation: staff enter its amount.
        if ($closure->isFullClosure()) {
            $closure->debt_foreclosure_quotation_id ??= $closure->liability->currentQuotation()?->getKey();
        }

        $confirmed = (float) $closure->payments()->whereNotNull('confirmed_at')->sum('amount');
        $required = (float) ($closure->final_closure_amount ?? ($closure->isFullClosure() ? $closure->quotation()->value('total_amount') : null) ?? 0);
        $nocDocument = $closure->noc_document_id ? DebtDocument::query()->find($closure->noc_document_id) : null;
        $errors = [];

        $needs = fn (ClosureStatus $status): bool => ($to->step() ?? 0) >= ($status->step() ?? PHP_INT_MAX);

        if ($to === ClosureStatus::QuotationReceived && $closure->liability->quotations()->doesntExist()) {
            $errors['status'] = 'Record the lender\'s foreclosure quotation for this loan first.';
        }

        if ($needs(ClosureStatus::PaymentInitiated) && $closure->payments()->doesntExist()) {
            $errors['status'] = 'Record the closure payment first.';
        }

        if ($needs(ClosureStatus::PaymentConfirmed)) {
            if ($required <= 0) {
                $errors['final_closure_amount'] = 'Enter the final closure amount (from the lender\'s quotation or statement) or record the quotation, so the payment can be checked against it.';
            } elseif ($confirmed <= 0) {
                $errors['status'] = 'Confirm the payment (with its UTR or proof) before moving on.';
            } elseif (round($confirmed, 2) < round($required, 2)) {
                $errors['status'] = 'Confirmed payments ('.number_format($confirmed, 2).') do not cover the closure amount ('.number_format($required, 2).').';
            }
        }

        if ($closure->isFullClosure() && $needs(ClosureStatus::NocReceived) && $nocDocument === null) {
            $errors['noc_document_id'] = 'Attach the NOC or closure letter from the lender.';
        }

        if ($to === ClosureStatus::ClosureVerified && $closure->isFullClosure() && $nocDocument?->status !== DocumentStatus::Verified) {
            $errors['noc_document_id'] = 'Verify the NOC / closure letter document before verifying the closure.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $completed = false;

        DB::transaction(function () use ($closure, $from, $to, $userId, $note, &$completed): void {
            $closure->status = $to;
            $closure->payment_initiated_at ??= ($to->step() ?? 0) >= 4 ? now() : null;
            $closure->payment_confirmed_at ??= ($to->step() ?? 0) >= 5 ? now() : null;
            $closure->closure_requested_at ??= ($to->step() ?? 0) >= 6 ? now() : null;
            $closure->noc_received_at ??= ($to->step() ?? 0) >= 8 && $closure->noc_document_id ? now() : null;

            if ($to === ClosureStatus::ClosureVerified) {
                $closure->verified_by = $userId;
                $closure->verified_at = now();
            }

            $closure->save();

            if ($from !== $to) {
                $closure->recordStatusChange($from->value, $to->value, ActorType::Staff, $userId, $note);
            }

            $completed = $this->completeCaseWhenAllClosed($closure->application);
        });

        if ($from !== $to) {
            $this->notifier->closureChanged($closure->fresh(['liability', 'application']), $to);
        }

        if ($completed) {
            $this->notifier->statusChanged($closure->application->fresh(), DebtApplicationStatus::Completed);
        }

        return $closure;
    }

    /**
     * The evidence for "NOC received" / "Closure verified" must be an NOC or
     * closure letter for THIS loan (or not yet tied to a loan), used by no
     * other closure.
     */
    private function guardNoc(DebtClosure $closure, DebtDocument $noc): void
    {
        if ($noc->debt_application_id !== $closure->debt_application_id) {
            throw ValidationException::withMessages(['noc_document_id' => 'That document belongs to another case.']);
        }

        if ($noc->documentType?->key !== 'noc_closure_letter') {
            throw ValidationException::withMessages(['noc_document_id' => 'Choose a document uploaded as an NOC / closure letter.']);
        }

        if ($noc->debt_liability_id !== null && $noc->debt_liability_id !== $closure->debt_liability_id) {
            throw ValidationException::withMessages(['noc_document_id' => 'That NOC is for another loan.']);
        }

        if (DebtClosure::query()->where('noc_document_id', $noc->getKey())->whereKeyNot($closure->getKey())->exists()) {
            throw ValidationException::withMessages(['noc_document_id' => 'That NOC is already the evidence for another closure.']);
        }
    }

    /**
     * Completes a case whose closures are all finished (at least one verified),
     * but only while it is in "Closure in progress": a case staff withdrew or
     * marked not proceeding stays as they left it.
     */
    private function completeCaseWhenAllClosed(DebtApplication $application): bool
    {
        if ($application->status !== DebtApplicationStatus::ClosureInProgress) {
            return false;
        }

        $statuses = $application->closures()->pluck('status');

        $allDone = $statuses->isNotEmpty() && $statuses->every(fn (ClosureStatus $status): bool => $status->isFinished());
        $anyVerified = $statuses->contains(ClosureStatus::ClosureVerified);

        if (! $allDone || ! $anyVerified) {
            return false;
        }

        $from = $application->status;
        $application->forceFill(['status' => DebtApplicationStatus::Completed, 'closed_at' => now()])->save();
        $application->recordStatusChange($from->value, DebtApplicationStatus::Completed->value, ActorType::System, null, 'Every existing loan in scope has a verified closure.');

        return true;
    }
}
