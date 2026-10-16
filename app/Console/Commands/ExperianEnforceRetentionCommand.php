<?php

namespace App\Console\Commands;

use App\Modules\Experian\Actions\FetchExperianPdf;
use App\Modules\Experian\Enums\ExperianRequestStatus;
use App\Modules\Experian\Models\ExperianCreditRequest;
use App\Modules\Experian\Services\ExperianSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Applies the Experian retention period (Experian Settings): older attempts lose
 * the provider response, summary, score, stored PDF and the customer's PAN,
 * name and mobile. The billing and audit record — masked PAN, product, status,
 * requester, provider ids, timestamps — stays, and successful results become
 * Expired. Attempts still awaiting reconciliation are left alone.
 */
class ExperianEnforceRetentionCommand extends Command
{
    protected $signature = 'experian:enforce-retention';

    protected $description = 'Purge Experian result contents older than the configured retention period';

    public function handle(): int
    {
        $before = now()->subDays(ExperianSettings::retentionDays());
        $purged = 0;

        ExperianCreditRequest::query()
            ->whereNull('purged_at')
            ->whereNotIn('status', ExperianRequestStatus::inFlight())
            ->where('requested_at', '<', $before)
            ->eachById(function (ExperianCreditRequest $request) use (&$purged): void {
                if (filled($request->pdf_path)) {
                    Storage::disk(FetchExperianPdf::DISK)->delete($request->pdf_path);
                }

                $request->update([
                    'status' => $request->status === ExperianRequestStatus::Success ? ExperianRequestStatus::Expired : $request->status,
                    'raw_response' => null,
                    'summary' => null,
                    'score' => null,
                    'pan_encrypted' => null,
                    'full_name' => null,
                    'mobile' => null,
                    'pdf_path' => null,
                    'purged_at' => now(),
                ]);

                $purged++;
            });

        $this->components->info("{$purged} Experian request(s) purged under the ".ExperianSettings::retentionDays().'-day retention period.');

        return self::SUCCESS;
    }
}
