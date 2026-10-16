<?php

namespace App\Console\Commands;

use App\Modules\Equifax\Actions\FetchEquifaxPdf;
use App\Modules\Equifax\Enums\EquifaxRequestStatus;
use App\Modules\Equifax\Models\EquifaxCreditRequest;
use App\Modules\Equifax\Services\EquifaxSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Applies the Equifax retention period (Equifax Settings): older attempts lose
 * the provider response, summary, score, stored PDF and the customer's PAN,
 * name and mobile. The billing and audit record — masked PAN, product, status,
 * requester, provider ids, timestamps — stays, and successful results become
 * Expired. Attempts still awaiting reconciliation are left alone.
 */
class EquifaxEnforceRetentionCommand extends Command
{
    protected $signature = 'equifax:enforce-retention';

    protected $description = 'Purge Equifax result contents older than the configured retention period';

    public function handle(): int
    {
        $before = now()->subDays(EquifaxSettings::retentionDays());
        $purged = 0;

        EquifaxCreditRequest::query()
            ->whereNull('purged_at')
            ->whereNotIn('status', EquifaxRequestStatus::inFlight())
            ->where('requested_at', '<', $before)
            ->eachById(function (EquifaxCreditRequest $request) use (&$purged): void {
                if (filled($request->pdf_path)) {
                    Storage::disk(FetchEquifaxPdf::DISK)->delete($request->pdf_path);
                }

                $request->update([
                    'status' => $request->status === EquifaxRequestStatus::Success ? EquifaxRequestStatus::Expired : $request->status,
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

        $this->components->info("{$purged} Equifax request(s) purged under the ".EquifaxSettings::retentionDays().'-day retention period.');

        return self::SUCCESS;
    }
}
