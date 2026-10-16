<?php

namespace App\Console\Commands;

use App\Modules\Equifax\Services\EquifaxSettings;
use App\Modules\Equifax\Services\SurepassEquifaxService;
use Illuminate\Console\Command;

/**
 * Answers "why is Equifax not working?" on a server in one command: is the
 * token loaded, can the server reach Surepass, and which Equifax endpoints does
 * the Surepass plan behind the token include. Free: every probe sends an EMPTY
 * body that Surepass rejects at validation or at the token check — no PAN or
 * name is sent and no report can be pulled.
 */
class EquifaxCheckAccessCommand extends Command
{
    protected $signature = 'equifax:check-access';

    protected $description = 'Check which Surepass Equifax APIs this server\'s token may use (free — pulls no report)';

    private const MEANING = [
        'allowed' => 'Allowed — your plan includes this API',
        'not_enabled' => 'NOT ENABLED on your Surepass plan — ask Surepass support to enable it',
        'token_rejected' => 'Token rejected — check the token named above is the full production token',
        'unreachable' => 'Unreachable — this server cannot connect to Surepass (firewall / DNS)',
        'not_found' => 'Endpoint not found — check the base URL above',
        'not_configured' => 'Token or base URL missing from the environment',
        'unexpected' => 'Unexpected answer — try again, then contact Surepass',
    ];

    public function handle(SurepassEquifaxService $surepass): int
    {
        $source = config('services.surepass.equifax_public_score_source') === 'score' ? 'score' : 'report';

        $this->components->twoColumnDetail('Base URL', $surepass->baseUrl());
        $this->components->twoColumnDetail('Token loaded', EquifaxSettings::isConfigured()
            ? 'yes ('.EquifaxSettings::tokenSource().')'
            : '<fg=red>NO</> — set SUREPASS_EQUIFAX_TOKEN or SUREPASS_BEARER_TOKEN, then php artisan config:clear');
        $this->components->twoColumnDetail('Equifax requests (admin / API)', EquifaxSettings::enabled() ? 'on' : 'off');
        $this->components->twoColumnDetail('Free Equifax Score page uses real scores', EquifaxSettings::publicScoreEnabled() ? 'on' : 'off');
        $this->components->twoColumnDetail('Public score source', $source);
        $this->newLine();

        $results = $surepass->checkAccess();

        $this->table(
            ['Endpoint', 'Used for', 'HTTP', 'Result'],
            collect($results)->map(fn (array $result, string $path): array => [
                $path,
                match ($path) {
                    SurepassEquifaxService::REPORT_PATH => 'Full reports'.($source === 'report' ? ' + public score page' : ''),
                    SurepassEquifaxService::SCORE_PATH => 'Score-only requests'.($source === 'score' ? ' + public score page' : ''),
                    default => 'PDF (when the report carries no usable link)',
                },
                $result['http_status'] ?? '—',
                self::MEANING[$result['access']] ?? $result['access'],
            ])->values()->all(),
        );

        if (($results[SurepassEquifaxService::REPORT_PATH]['access'] ?? null) !== 'allowed') {
            $this->components->error('Equifax full reports will fail until the fetch-report endpoint shows "Allowed".');

            return self::FAILURE;
        }

        $publicPath = $source === 'score' ? SurepassEquifaxService::SCORE_PATH : SurepassEquifaxService::REPORT_PATH;

        if (($results[$publicPath]['access'] ?? null) !== 'allowed') {
            $this->components->warn('The public Equifax score page uses an endpoint your plan does not include. Set SUREPASS_EQUIFAX_PUBLIC_SCORE_SOURCE=report and clear the config cache.');

            return self::FAILURE;
        }

        if (($results[SurepassEquifaxService::SCORE_PATH]['access'] ?? null) !== 'allowed') {
            $this->components->warn('Score-only requests will fail until Surepass enables credit-report-v2/score; a recent full report still answers them.');
        }

        $this->components->info('Full reports and the public score page can use this token.');

        return self::SUCCESS;
    }
}
