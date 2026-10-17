<?php

namespace App\Console\Commands;

use App\Modules\Cibil\Services\CibilSettings;
use App\Modules\Cibil\Services\SurepassCibilService;
use Illuminate\Console\Command;

/**
 * Answers "why is CIBIL not working?" on a server in one command: is the
 * token loaded, can the server reach Surepass, and which CIBIL endpoints does
 * the Surepass plan behind the token include. Free: every probe sends an EMPTY
 * body that Surepass rejects at validation or at the token check — no PAN or
 * name is sent and no report can be pulled.
 */
class CibilCheckAccessCommand extends Command
{
    protected $signature = 'cibil:check-access';

    protected $description = 'Check which Surepass CIBIL APIs this server\'s token may use (free — pulls no report)';

    private const MEANING = [
        'allowed' => 'Allowed — your plan includes this API',
        'not_enabled' => 'NOT ENABLED on your Surepass plan — ask Surepass support to enable it',
        'token_rejected' => 'Token rejected — check the token named above is the full production token',
        'unreachable' => 'Unreachable — this server cannot connect to Surepass (firewall / DNS)',
        'not_found' => 'Endpoint not found — check the base URL above',
        'not_configured' => 'Token or base URL missing from the environment',
        'unexpected' => 'Unexpected answer — try again, then contact Surepass',
    ];

    public function handle(SurepassCibilService $surepass): int
    {
        $source = config('services.surepass.cibil_public_score_endpoint') === 'score' ? 'score' : 'report';

        $this->components->twoColumnDetail('Base URL', $surepass->baseUrl());
        $this->components->twoColumnDetail('Token loaded', CibilSettings::isConfigured()
            ? 'yes ('.CibilSettings::tokenSource().')'
            : '<fg=red>NO</> — set SUREPASS_CIBIL_TOKEN (CIBIL never uses another bureau\'s token), then php artisan config:clear');
        $this->components->twoColumnDetail('CIBIL requests (admin / API)', CibilSettings::enabled() ? 'on' : 'off');
        $this->components->twoColumnDetail('Get CIBIL Score page: Real CIBIL score enabled', CibilSettings::publicScoreEnabled() ? 'on' : 'off');
        $this->components->twoColumnDetail('Get CIBIL Score page: Fetch Experian instead of CIBIL', CibilSettings::publicScoreUsesExperian() ? 'on' : 'off');
        $this->components->twoColumnDetail('Get CIBIL Score page shows', match (true) {
            ! CibilSettings::publicScoreEnabled() => 'demo scores (no bureau is called)',
            ! CibilSettings::publicSourceConfigured() => '<fg=red>demo scores</> — the '.CibilSettings::sourceLabel().' token is not set',
            default => 'real '.CibilSettings::sourceLabel().' scores',
        });
        $this->components->twoColumnDetail('Public score endpoint (when answered by CIBIL)', $source);
        $this->newLine();

        $results = $surepass->checkAccess();

        $this->table(
            ['Endpoint', 'Used for', 'HTTP', 'Result'],
            collect($results)->map(fn (array $result, string $path): array => [
                $path,
                match ($path) {
                    SurepassCibilService::REPORT_PATH => 'Full reports'.($source === 'report' ? ' + public score page' : ''),
                    SurepassCibilService::SCORE_PATH => 'Score-only requests'.($source === 'score' ? ' + public score page' : ''),
                    default => 'PDF report requests',
                },
                $result['http_status'] ?? '—',
                self::MEANING[$result['access']] ?? $result['access'],
            ])->values()->all(),
        );

        if (($results[SurepassCibilService::REPORT_PATH]['access'] ?? null) !== 'allowed') {
            $this->components->error('CIBIL full reports will fail until the fetch-report endpoint shows "Allowed".');

            return self::FAILURE;
        }

        $publicPath = $source === 'score' ? SurepassCibilService::SCORE_PATH : SurepassCibilService::REPORT_PATH;

        if (($results[$publicPath]['access'] ?? null) !== 'allowed') {
            $this->components->warn('The public CIBIL score page uses an endpoint your plan does not include. Set SUREPASS_CIBIL_PUBLIC_SCORE_ENDPOINT=report and clear the config cache.');

            return self::FAILURE;
        }

        if (($results[SurepassCibilService::SCORE_PATH]['access'] ?? null) !== 'allowed') {
            $this->components->warn('Score-only requests will fail until Surepass enables credit-report-cibil/score; a recent full report still answers them.');
        }

        if (($results[SurepassCibilService::PDF_PATH]['access'] ?? null) !== 'allowed') {
            $this->components->warn('PDF report requests will fail until Surepass enables credit-report-cibil/fetch-report-pdf (nothing is charged for those failures).');
        }

        $this->components->info('Full reports and the public score page can use this token.');

        return self::SUCCESS;
    }
}
