<?php

namespace App\Console\Commands;

use App\Models\NotFoundUrl;
use App\Models\Redirect;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * The Missing pages (404) list on the command line, for a server or a cron
 * job: what is being asked for that does not exist, read from the
 * not_found_urls table RecordNotFoundRequests fills. Read-only.
 *
 * Paths that already have a rule in Redirects are left out: they no longer
 * 404, and only stay in the table until they age out.
 *
 * Exits non-zero when a page on this site linked to a missing page within
 * the window, so it can alert through cron's MAILTO or a CI step without any
 * new notification service. Everything else is information.
 */
class SeoNotFoundReportCommand extends Command
{
    protected $signature = 'seo:not-found
                            {--days=7 : Report paths requested within this many days}
                            {--limit=20 : Show at most this many paths per section}';

    protected $description = 'List the missing pages (404) visitors and crawlers asked for recently, and fail when the site links to one';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $limit = max(1, (int) $this->option('limit'));
        $since = now()->subDays($days);
        $ruleSources = Redirect::activeMap();

        $recent = NotFoundUrl::query()
            ->where('last_seen_at', '>=', $since)
            ->orderByDesc('hits')
            ->orderBy('id')
            ->get()
            ->reject(fn (NotFoundUrl $url): bool => isset($ruleSources[$url->path]));

        $linkedInternally = $recent->filter(fn (NotFoundUrl $url): bool => $url->internal_referrals > 0);
        $new = $recent->filter(fn (NotFoundUrl $url): bool => $url->first_seen_at->greaterThanOrEqualTo($since));

        $this->components->twoColumnDetail("Missing paths requested in the last {$days} days", (string) $recent->count());
        $this->components->twoColumnDetail('First seen in that time', (string) $new->count());
        $this->components->twoColumnDetail('Linked from this site', (string) $linkedInternally->count());

        $this->section('Linked from this site: fix the link where it is, or add a rule', $linkedInternally, $limit);
        $this->section('Most requested', $recent, $limit);
        $this->section('New', $new, $limit);

        if ($linkedInternally->isNotEmpty()) {
            $this->components->error("{$linkedInternally->count()} missing page(s) are linked from this site.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, NotFoundUrl>  $urls
     */
    private function section(string $title, Collection $urls, int $limit): void
    {
        if ($urls->isEmpty()) {
            return;
        }

        $this->newLine();
        $this->line("<options=bold>{$title}</>");
        $this->table(
            ['Path', 'Requests', 'From this site', 'Crawlers', 'Last linked from', 'Last seen'],
            $urls->take($limit)->map(fn (NotFoundUrl $url): array => [
                $url->path,
                $url->hits,
                $url->internal_referrals,
                $url->crawler_hits,
                $url->last_referrer ?? '—',
                $url->last_seen_at->format('d M Y H:i'),
            ])->all(),
        );
    }
}
