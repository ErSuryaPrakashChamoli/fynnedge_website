<?php

namespace App\Console\Commands;

use App\Modules\Cibil\Actions\FetchCibilPdf;
use App\Modules\Cibil\Enums\CibilRequestStatus;
use App\Modules\Cibil\Models\CibilCreditRequest;
use App\Modules\Cibil\Services\CibilSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Applies the CIBIL retention period (CIBIL Settings): older attempts lose
 * the provider response, summary, score, stored PDF and the customer's PAN,
 * name and mobile. The billing and audit record — masked PAN, product, status,
 * requester, provider ids, timestamps — stays, and successful results become
 * Expired. Attempts still awaiting reconciliation are left alone.
 */
class CibilEnforceRetentionCommand extends Command
{
    protected $signature = 'cibil:enforce-retention';

    protected $description = 'Purge CIBIL result contents older than the configured retention period';

    public function handle(): int
    {
        $before = now()->subDays(CibilSettings::retentionDays());
        $purged = 0;

        CibilCreditRequest::query()
            ->whereNull('purged_at')
            ->whereNotIn('status', CibilRequestStatus::inFlight())
            ->where('requested_at', '<', $before)
            ->eachById(function (CibilCreditRequest $request) use (&$purged): void {
                if (filled($request->pdf_path)) {
                    Storage::disk(FetchCibilPdf::DISK)->delete($request->pdf_path);
                }

                $request->update([
                    'status' => $request->status === CibilRequestStatus::Success ? CibilRequestStatus::Expired : $request->status,
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

        $this->components->info("{$purged} CIBIL request(s) purged under the ".CibilSettings::retentionDays().'-day retention period.');

        return self::SUCCESS;
    }
}
