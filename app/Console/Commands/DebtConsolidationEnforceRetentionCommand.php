<?php

namespace App\Console\Commands;

use App\Modules\DebtConsolidation\Enums\DebtApplicationStatus;
use App\Modules\DebtConsolidation\Models\DebtApplication;
use App\Modules\DebtConsolidation\Models\DebtCalculationSnapshot;
use Illuminate\Console\Command;

/**
 * Debt Consolidation retention (docs/debt-consolidation/09-security-compliance.md):
 *  - an unfinished draft (never submitted, not even as an enquiry) with no
 *    customer activity for `drafts_days` is withdrawn and soft-deleted;
 *  - an anonymous calculator snapshot (never attached to a case) older than
 *    `calculator_snapshots_days` is deleted.
 * Submitted cases, their documents and reports are never touched here: their
 * retention follows the business's record-keeping policy (an outstanding
 * decision), so nothing is deleted automatically.
 */
class DebtConsolidationEnforceRetentionCommand extends Command
{
    protected $signature = 'debt-consolidation:enforce-retention {--dry-run : Report what would be removed without removing it}';

    protected $description = 'Withdraw abandoned debt consolidation drafts and delete old anonymous calculator snapshots';

    public function handle(): int
    {
        $draftsBefore = now()->subDays((int) config('debt_consolidation.retention.drafts_days'));
        $snapshotsBefore = now()->subDays((int) config('debt_consolidation.retention.calculator_snapshots_days'));

        $drafts = DebtApplication::query()
            ->where('status', DebtApplicationStatus::Draft)
            ->whereNull('enquiry_submitted_at')
            ->whereNull('submitted_at')
            ->where(fn ($query) => $query->where('last_customer_activity_at', '<', $draftsBefore)->orWhere(fn ($query) => $query->whereNull('last_customer_activity_at')->where('created_at', '<', $draftsBefore)));
        $snapshots = DebtCalculationSnapshot::query()->whereNull('debt_application_id')->where('created_at', '<', $snapshotsBefore);

        $draftCount = (clone $drafts)->count();
        $snapshotCount = (clone $snapshots)->count();

        if (! $this->option('dry-run')) {
            $drafts->each(function (DebtApplication $application): void {
                $application->forceFill(['status' => DebtApplicationStatus::Withdrawn, 'closed_at' => now(), 'closure_reason' => 'Abandoned draft removed by retention policy'])->save();
                $application->delete();
            });
            $snapshots->delete();
        }

        $this->info(($this->option('dry-run') ? 'Would remove' : 'Removed')." {$draftCount} abandoned draft(s) and {$snapshotCount} anonymous calculator snapshot(s).");

        return self::SUCCESS;
    }
}
