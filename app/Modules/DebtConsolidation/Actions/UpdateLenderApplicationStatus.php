<?php

namespace App\Modules\DebtConsolidation\Actions;

use App\Modules\DebtConsolidation\Enums\ActorType;
use App\Modules\DebtConsolidation\Enums\ClosureStatus;
use App\Modules\DebtConsolidation\Enums\ConsolidationMode;
use App\Modules\DebtConsolidation\Enums\DebtApplicationStatus;
use App\Modules\DebtConsolidation\Enums\LenderApplicationStatus;
use App\Modules\DebtConsolidation\Models\DebtLenderApplication;
use App\Modules\DebtConsolidation\Models\DebtLiability;
use App\Modules\DebtConsolidation\Notifications\DebtCaseNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records what a lender communicated about an application. Every status
 * from Submitted on comes from the lender and is entered by staff with the
 * evidence it needs; nothing here approves anything.
 *
 *  - Submitted needs the customer's lender-sharing consent still active.
 *  - Approved / Sanction letter issued / Disbursed need a submitted
 *    application; Approved needs a note of how the lender confirmed it;
 *    Sanction letter issued needs the sanctioned amount; Disbursed needs
 *    the disbursed amount and date and a prior approval or sanction.
 *  - Rejected needs the lender's reason.
 *  - Final states (rejected, disbursed, withdrawn) cannot be changed.
 *
 * The case moves to "With lender" on its first submission and to "Closing
 * existing loans" on the first disbursement, when a closure tracker is
 * opened for every liability in scope. Liabilities are NOT marked closed.
 */
class UpdateLenderApplicationStatus
{
    public function __construct(private readonly DebtCaseNotifier $notifier) {}

    /**
     * @param  array{lender_reference?: ?string, sanctioned_amount?: ?float, sanctioned_rate?: ?float, sanctioned_tenure_months?: ?int, processing_fee_amount?: ?float, disbursed_amount?: ?float, disbursed_at?: mixed, rejection_reason?: ?string}  $details
     */
    public function handle(DebtLenderApplication $lenderApplication, LenderApplicationStatus $to, int $userId, ?string $note = null, array $details = []): DebtLenderApplication
    {
        $from = $lenderApplication->status;

        if ($from->isFinal() && $from !== $to) {
            throw ValidationException::withMessages(['status' => "A {$from->getLabel()} application cannot be changed. Open a new lender application instead."]);
        }

        $details = array_filter($details, fn (mixed $value): bool => $value !== null && $value !== '');
        $merged = [...$lenderApplication->only(['sanctioned_amount', 'disbursed_amount', 'disbursed_at', 'rejection_reason', 'submitted_at']), ...$details];
        $errors = [];

        // Consent governs SHARING the case with the lender, i.e. submitting it. What the lender
        // decides afterwards (review, rejection, sanction, disbursal) is always recorded, even
        // when the customer has since withdrawn consent.
        if ($to === LenderApplicationStatus::Submitted && ! ($lenderApplication->consent?->isActive() ?? false)) {
            $errors['status'] = 'The customer\'s consent to share with this lender is missing or withdrawn.';
        }

        if (in_array($to, [LenderApplicationStatus::Approved, LenderApplicationStatus::SanctionLetterIssued, LenderApplicationStatus::Disbursed, LenderApplicationStatus::UnderReview, LenderApplicationStatus::CreditAssessment, LenderApplicationStatus::AdditionalDocumentsRequired, LenderApplicationStatus::Rejected], true) && $lenderApplication->submitted_at === null && $from === LenderApplicationStatus::NotSubmitted) {
            $errors['status'] = 'Mark the application as submitted to the lender first.';
        }

        if ($to === LenderApplicationStatus::Approved && blank($note)) {
            $errors['note'] = 'Record how the lender confirmed the approval (e.g. email from the lender on a date).';
        }

        if ($to === LenderApplicationStatus::SanctionLetterIssued && empty($merged['sanctioned_amount'])) {
            $errors['sanctioned_amount'] = 'Enter the sanctioned amount from the sanction letter.';
        }

        if ($to === LenderApplicationStatus::Disbursed) {
            if (empty($merged['disbursed_amount']) || empty($merged['disbursed_at'])) {
                $errors['disbursed_amount'] = 'Enter the disbursed amount and date confirmed by the lender.';
            }

            if (! in_array($from, [LenderApplicationStatus::Approved, LenderApplicationStatus::SanctionLetterIssued], true)) {
                $errors['status'] = 'A loan can only be marked disbursed after the lender approved or sanctioned it.';
            }
        }

        if ($to === LenderApplicationStatus::Rejected && empty($merged['rejection_reason'])) {
            $errors['rejection_reason'] = 'Record the reason the lender gave.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        DB::transaction(function () use ($lenderApplication, $from, $to, $userId, $note, $details): void {
            $lenderApplication->fill($details);
            $lenderApplication->status = $to;
            $lenderApplication->submitted_at ??= $to->isSubmitted() && $to !== LenderApplicationStatus::Withdrawn ? now() : null;
            $lenderApplication->sanctioned_at ??= $to === LenderApplicationStatus::SanctionLetterIssued ? now() : null;
            $lenderApplication->decision_at = in_array($to, [LenderApplicationStatus::Approved, LenderApplicationStatus::Rejected, LenderApplicationStatus::SanctionLetterIssued], true) ? now() : $lenderApplication->decision_at;
            $lenderApplication->save();

            if ($from !== $to) {
                $lenderApplication->recordStatusChange($from->value, $to->value, ActorType::Staff, $userId, $note);
            }

            $application = $lenderApplication->application;

            if ($to === LenderApplicationStatus::Submitted && in_array($application->status, [DebtApplicationStatus::ReadyForSubmission, DebtApplicationStatus::CustomerConfirmation, DebtApplicationStatus::LenderMatching, DebtApplicationStatus::AssessmentCompleted], true)) {
                $this->moveCase($application, DebtApplicationStatus::LenderProcessing, 'First application submitted to a lender.');
            }

            if ($to === LenderApplicationStatus::Disbursed) {
                $this->openClosures($lenderApplication);

                if ($application->status->isOpen() && $application->status !== DebtApplicationStatus::ClosureInProgress) {
                    $this->moveCase($application, DebtApplicationStatus::ClosureInProgress, 'Lender disbursed; existing loans to be closed.');
                }
            }
        });

        if ($from !== $to) {
            $this->notifier->lenderApplicationChanged($lenderApplication->fresh(), $to);
        }

        return $lenderApplication;
    }

    private function openClosures(DebtLenderApplication $lenderApplication): void
    {
        $lenderApplication->application->liabilities()
            ->where('consolidation_mode', '!=', ConsolidationMode::Exclude)
            ->whereDoesntHave('closure')
            ->get()
            ->each(function (DebtLiability $liability) use ($lenderApplication): void {
                $closure = $liability->closure()->create([
                    'debt_application_id' => $liability->debt_application_id,
                    'debt_lender_application_id' => $lenderApplication->getKey(),
                    'closure_type' => $liability->consolidation_mode === ConsolidationMode::Partial ? ConsolidationMode::Partial : ConsolidationMode::Full,
                    'status' => ClosureStatus::Planned,
                    'debt_foreclosure_quotation_id' => $liability->consolidation_mode === ConsolidationMode::Partial ? null : $liability->currentQuotation()?->getKey(),
                ]);

                $closure->recordStatusChange(null, ClosureStatus::Planned->value, ActorType::System);
            });
    }

    private function moveCase($application, DebtApplicationStatus $to, string $note): void
    {
        $from = $application->status;
        $application->forceFill(['status' => $to])->save();
        $application->recordStatusChange($from->value, $to->value, ActorType::System, null, $note);
    }
}
