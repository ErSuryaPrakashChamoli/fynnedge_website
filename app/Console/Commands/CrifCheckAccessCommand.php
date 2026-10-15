<?php

namespace App\Console\Commands;

use App\Modules\CreditBureau\Services\CrifSettings;
use App\Modules\CreditBureau\Services\SurepassCrifService;
use Illuminate\Console\Command;

/**
 * Answers "why is CRIF not working?" on a server in one command: is the token
 * loaded, can the server reach Surepass, and which CRIF endpoints does the
 * Surepass plan behind the token actually include.
 *
 * Free and safe to run any time: every probe sends an EMPTY body, which Surepass
 * rejects at input validation or at the token check — no PAN or name is sent
 * and no credit report can be pulled.
 */
class CrifCheckAccessCommand extends Command
{
    protected $signature = 'crif:check-access';

    protected $description = 'Check which Surepass CRIF APIs this server\'s token may use (free — pulls no report)';

    private const MEANING = [
        'allowed' => 'Allowed — your plan includes this API',
        'not_enabled' => 'NOT ENABLED on your Surepass plan — ask Surepass support to enable it',
        'token_rejected' => 'Token rejected — check SUREPASS_BEARER_TOKEN is the full production token',
        'unreachable' => 'Unreachable — this server cannot connect to Surepass (firewall / DNS)',
        'not_found' => 'Endpoint not found — check SUREPASS_BASE_URL',
        'not_configured' => 'Token or base URL missing from the environment',
        'unexpected' => 'Unexpected answer — try again, then contact Surepass',
    ];

    public function handle(SurepassCrifService $surepass): int
    {
        $this->components->twoColumnDetail('Base URL', (string) config('services.surepass.base_url'));
        $this->components->twoColumnDetail('Token loaded', CrifSettings::isConfigured() ? 'yes' : '<fg=red>NO</>');
        $this->components->twoColumnDetail('CRIF report requests (admin)', CrifSettings::enabled() ? 'on' : 'off');
        $this->components->twoColumnDetail('Free CRIF Score page uses real scores', CrifSettings::publicScoreEnabled() ? 'on' : 'off');
        $this->components->twoColumnDetail('Public score source', (string) config('services.surepass.crif_public_score_source'));
        $this->newLine();

        $results = $surepass->checkAccess();

        $this->table(
            ['Endpoint', 'Used for', 'HTTP', 'Result'],
            collect($results)->map(fn (array $result, string $path): array => [
                $path,
                match ($path) {
                    SurepassCrifService::REPORT_PATH => 'Admin full reports'.(config('services.surepass.crif_public_score_source') === 'score' ? '' : ' + public score page'),
                    SurepassCrifService::SCORE_PATH => config('services.surepass.crif_public_score_source') === 'score' ? 'Public score page' : '(not used — source is "report")',
                    default => 'PDF button (when the report has no embedded PDF)',
                },
                $result['http_status'] ?? '—',
                self::MEANING[$result['access']] ?? $result['access'],
            ])->values()->all(),
        );

        $publicPath = config('services.surepass.crif_public_score_source') === 'score' ? SurepassCrifService::SCORE_PATH : SurepassCrifService::REPORT_PATH;

        if (($results[SurepassCrifService::REPORT_PATH]['access'] ?? null) !== 'allowed') {
            $this->components->error('Full CRIF reports will fail until the fetch-report endpoint shows "Allowed".');

            return self::FAILURE;
        }

        if (($results[$publicPath]['access'] ?? null) !== 'allowed') {
            $this->components->warn('The public score page uses an endpoint your plan does not include. Set SUREPASS_CRIF_PUBLIC_SCORE_SOURCE=report and clear the config cache.');

            return self::FAILURE;
        }

        $this->components->info('Everything the site uses is available to this token.');

        return self::SUCCESS;
    }
}
