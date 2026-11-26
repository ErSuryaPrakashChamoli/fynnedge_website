<?php

namespace App\Modules\DebtConsolidation\Actions;

use App\Modules\DebtConsolidation\Enums\ActorType;
use App\Modules\DebtConsolidation\Enums\DebtApplicationStatus;
use App\Modules\DebtConsolidation\Models\DebtApplication;
use App\Modules\DebtConsolidation\Notifications\DebtCaseNotifier;
use Illuminate\Validation\ValidationException;

/**
 * Staff change of FynnEdge's own case status. "Completed" is never set by
 * hand — it follows from every in-scope closure being verified — and a case
 * cannot go back to the customer's unfinished "Draft". Closing a case
 * (not proceeding / withdrawn) needs a reason.
 */
class ChangeDebtApplicationStatus
{
    public function __construct(private readonly DebtCaseNotifier $notifier) {}

    public function handle(DebtApplication $application, DebtApplicationStatus $to, int $userId, ?string $note = null): DebtApplication
    {
        if (! in_array($to, DebtApplicationStatus::staffSelectable(), true)) {
            throw ValidationException::withMessages(['status' => 'This status is set automatically and cannot be chosen.']);
        }

        if (in_array($to, [DebtApplicationStatus::NotProceeding, DebtApplicationStatus::Withdrawn], true) && blank($note)) {
            throw ValidationException::withMessages(['note' => 'Give the reason the case is closed.']);
        }

        $from = $application->status;

        if ($from === $to) {
            return $application;
        }

        $application->forceFill([
            'status' => $to,
            'closed_at' => $to->isOpen() ? null : now(),
            'closure_reason' => $to->isOpen() ? null : $note,
        ])->save();

        $application->recordStatusChange($from->value, $to->value, ActorType::Staff, $userId, $note);
        $this->notifier->statusChanged($application, $to);

        return $application;
    }
}
