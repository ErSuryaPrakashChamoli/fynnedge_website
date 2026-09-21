<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One command an operator can run on a server to answer "my CSV upload did
 * nothing, what happened?".
 *
 * A Filament import only enqueues jobs. With no queue worker running, the
 * upload looks successful, the imports row stays at processed_rows=0 and
 * nothing is written to the log — so the state has to be read out of the
 * jobs/imports tables before anything can be done about it.
 */
class ProcessQueuedImportsCommand extends Command
{
    protected $signature = 'imports:process
                            {--check : Only report the current state, do not process anything}';

    protected $description = 'Show CSV import progress and the queue backlog, then process the queue until it is empty';

    public function handle(): int
    {
        $this->reportImports();
        $pendingByType = $this->reportQueue();

        if ($this->option('check')) {
            return self::SUCCESS;
        }

        if (array_sum($pendingByType) === 0) {
            $this->components->info('Nothing is waiting in the queue.');

            if ($this->hasUnfinishedImport()) {
                $this->components->warn('An import is still unfinished but has no jobs left. Check `php artisan queue:failed`.');
            }

            return self::SUCCESS;
        }

        /*
         * Draining the queue delivers any queued mail as well, which on a live
         * server means real messages — including old ones that have been
         * waiting since the last time a worker ran.
         */
        $mailJobs = $this->countMailJobs($pendingByType);

        if ($mailJobs > 0) {
            $this->components->warn("{$mailJobs} queued mail job(s) will be SENT as part of this run.");

            if (! $this->confirm('Continue?', true)) {
                return self::FAILURE;
            }
        }

        $this->components->info('Processing the queue until it is empty...');

        $this->call('queue:work', ['--stop-when-empty' => true]);

        $this->components->info('Queue drained. Current state:');
        $this->reportImports();
        $this->reportQueue();

        return self::SUCCESS;
    }

    private function reportImports(): void
    {
        $imports = DB::table('imports')->orderByDesc('id')->limit(5)->get();

        if ($imports->isEmpty()) {
            $this->components->info('No CSV imports have been started.');

            return;
        }

        $this->table(
            ['Import', 'File', 'Rows done', 'Successful', 'Finished'],
            $imports->map(fn (object $import): array => [
                $import->id,
                $import->file_name,
                "{$import->processed_rows}/{$import->total_rows}",
                $import->successful_rows,
                $import->completed_at ?? 'NOT FINISHED',
            ])->all(),
        );
    }

    /**
     * @return array<string, int>
     */
    private function reportQueue(): array
    {
        $pendingByType = DB::table('jobs')
            ->pluck('payload')
            ->map(fn (string $payload): string => json_decode($payload, true)['displayName'] ?? 'unknown')
            ->countBy()
            ->all();

        $running = DB::table('jobs')->whereNotNull('reserved_at')->count();
        $failed = DB::table('failed_jobs')->count();

        $this->line(sprintf(
            '  Queue: <info>%d</info> waiting, <info>%d</info> in progress, <info>%d</info> failed',
            array_sum($pendingByType),
            $running,
            $failed,
        ));

        foreach ($pendingByType as $type => $count) {
            $this->line("    {$count} x {$type}");
        }

        return $pendingByType;
    }

    /**
     * @param  array<string, int>  $pendingByType
     */
    private function countMailJobs(array $pendingByType): int
    {
        $total = 0;

        foreach ($pendingByType as $type => $count) {
            if (str_contains($type, 'Mail') || str_contains($type, 'Notification')) {
                $total += $count;
            }
        }

        return $total;
    }

    private function hasUnfinishedImport(): bool
    {
        return DB::table('imports')->whereNull('completed_at')->exists();
    }
}
