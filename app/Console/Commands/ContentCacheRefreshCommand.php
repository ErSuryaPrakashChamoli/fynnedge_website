<?php

namespace App\Console\Commands;

use App\Enums\ContentCacheSection;
use App\Support\ContentCache\ContentCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Invalidates named sections of the content cache — for a change made behind
 * the application's back (raw SQL on the server), never as part of normal
 * editing, which invalidates by itself.
 *
 * Granular on purpose: it bumps section versions, so only that section's
 * entries go cold, and it never runs a cache flush (which on Redis would also
 * drop locks and rate-limit counters). Every run is logged with who ran it.
 */
class ContentCacheRefreshCommand extends Command
{
    protected $signature = 'content-cache:refresh
                            {sections?* : Sections to invalidate (see content-cache:status)}
                            {--all : Invalidate every section}';

    protected $description = 'Invalidate sections of the public content cache so pages re-read them from the database';

    public function handle(): int
    {
        $names = $this->option('all')
            ? array_column(ContentCacheSection::cases(), 'value')
            : (array) $this->argument('sections');

        if ($names === []) {
            $this->components->error('Name the sections to invalidate, or pass --all. Sections: '.implode(', ', array_column(ContentCacheSection::cases(), 'value')).'.');

            return self::FAILURE;
        }

        $unknown = array_filter($names, fn (string $name): bool => ContentCacheSection::tryFrom($name) === null);

        if ($unknown !== []) {
            $this->components->error('Unknown section(s): '.implode(', ', $unknown).'.');

            return self::FAILURE;
        }

        ContentCache::invalidate(...array_map(ContentCacheSection::from(...), $names));

        Log::notice('Content cache sections invalidated by hand.', ['sections' => $names, 'by' => get_current_user()]);
        $this->components->info('Invalidated: '.implode(', ', $names).'. The next request rebuilds them from the database.');

        return self::SUCCESS;
    }
}
