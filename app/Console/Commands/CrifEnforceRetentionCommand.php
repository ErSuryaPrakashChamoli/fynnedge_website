<?php

namespace App\Console\Commands;

use App\Modules\CreditBureau\Actions\FetchCrifReportPdf;
use App\Modules\CreditBureau\Enums\CrifReportStatus;
use App\Modules\CreditBureau\Models\CrifReport;
use App\Modules\CreditBureau\Services\CrifSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Applies the CRIF retention period (CRIF Settings): once an attempt is older
 * than it, the report contents, score, stored PDF and the customer's PAN, names
 * and mobile are erased. What stays is the billing and audit record — masked
 * PAN, status, who requested it, provider ids and timestamps — and successful
 * reports become Expired. Attempts still awaiting reconciliation are left alone.
 */
class CrifEnforceRetentionCommand extends Command
{
    protected $signature = 'crif:enforce-retention';

    protected $description = 'Purge CRIF report contents older than the configured retention period';

    public function handle(): int
    {
        $before = now()->subDays(CrifSettings::retentionDays());
        $purged = 0;

        CrifReport::query()
            ->whereNull('purged_at')
            ->whereNotIn('status', CrifReportStatus::inFlight())
            ->where('requested_at', '<', $before)
            ->eachById(function (CrifReport $report) use (&$purged): void {
                if (filled($report->pdf_path)) {
                    Storage::disk(FetchCrifReportPdf::DISK)->delete($report->pdf_path);
                }

                $report->update([
                    'status' => $report->status === CrifReportStatus::Success ? CrifReportStatus::Expired : $report->status,
                    'raw_response' => null,
                    'score' => null,
                    'pan_encrypted' => null,
                    'first_name' => null,
                    'last_name' => null,
                    'mobile' => null,
                    'pdf_path' => null,
                    'purged_at' => now(),
                ]);

                $purged++;
            });

        $this->components->info("{$purged} CRIF report(s) purged under the ".CrifSettings::retentionDays().'-day retention period.');

        return self::SUCCESS;
    }
}
