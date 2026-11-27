<?php

namespace App\Console\Commands;

use App\Enums\ContentCacheSection;
use App\Enums\LenderStatus;
use App\Enums\PublishStatus;
use App\Models\Article;
use App\Models\AuditLog;
use App\Models\Faq;
use App\Models\Lender;
use App\Models\LenderProduct;
use App\Models\LoanLandingPage;
use App\Models\LoanProduct;
use App\Models\Page;
use App\Models\Testimonial;
use App\Support\ContentCache\ContentCache;
use App\Support\Localization\AutoTranslation;
use App\Support\Pages\DebtConsolidationPages;
use BackedEnum;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Reverts what an unattended process (a seeder, console code: anything the
 * audit log records without a signed-in user) wrote to website content in a
 * time window, field by field, from the audit log's own "old" values.
 *
 * Built after 2026-11-26, when a bare `php artisan db:seed --force` replaced
 * production's loan products, landing pages, About, legal pages and articles
 * with starter text. It only restores a field whose current value is still
 * exactly what the process wrote, so anything edited since is kept and listed.
 * Debt Consolidation pages and their FAQs are never touched. It previews by
 * default; --apply asks for confirmation and writes a JSON backup of every
 * record it is about to change first.
 */
class RestoreContentFromAuditLogCommand extends Command
{
    protected $signature = 'content:restore-from-audit-log
                            {--from= : Start of the window, in UTC, e.g. "2026-11-26 13:09:00"}
                            {--to= : End of the window, in UTC, e.g. "2026-11-26 13:09:59"}
                            {--apply : Make the changes. Without it the command only previews them}
                            {--hide-created : Also hide content the process created in the window (Draft, or Inactive for lenders and offers)}';

    protected $description = 'Preview or revert, field by field, unattended changes to website content in a time window, using the audit log';

    /**
     * Website content the audit log records in full (no auditExcept).
     *
     * @var list<class-string<Model>>
     */
    private const CONTENT_MODELS = [
        LoanProduct::class, LoanLandingPage::class, Page::class, Article::class,
        Faq::class, Testimonial::class, Lender::class, LenderProduct::class,
    ];

    public function handle(): int
    {
        $window = $this->window();

        if ($window === null) {
            return self::FAILURE;
        }

        [$from, $to] = $window;

        $logs = AuditLog::query()
            ->whereNull('user_id')
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('id')
            ->get();

        $otherTypes = $logs->reject(fn (AuditLog $log): bool => in_array($log->auditable_type, self::CONTENT_MODELS, true))
            ->countBy('auditable_type');
        $contentLogs = $logs->filter(fn (AuditLog $log): bool => in_array($log->auditable_type, self::CONTENT_MODELS, true));

        [$restorations, $kept, $skippedRecords] = $this->planRestorations($contentLogs->where('action', 'updated'));
        [$toHide, $createdKept] = $this->planHiding($contentLogs->where('action', 'created'), $to);

        $this->report($from, $to, $restorations, $kept, $skippedRecords, $toHide, $createdKept, $otherTypes);

        if (! $this->option('apply')) {
            $this->components->info('Preview only: nothing was changed. Re-run with --apply to make these changes.');

            return self::SUCCESS;
        }

        $hiding = $this->option('hide-created') ? $toHide : collect();

        if ($restorations->isEmpty() && $hiding->isEmpty()) {
            $this->components->info('Nothing to restore.');

            return self::SUCCESS;
        }

        if (! $this->confirm("Restore {$restorations->count()} record(s) and hide {$hiding->count()} created record(s)?", false)) {
            $this->components->warn('Cancelled: nothing was changed.');

            return self::FAILURE;
        }

        $backupPath = $this->backUp($restorations, $hiding);
        $this->apply($restorations, $hiding);

        Log::notice('Website content restored from the audit log.', [
            'window' => [$from->toDateTimeString(), $to->toDateTimeString()],
            'records_restored' => $restorations->count(),
            'fields_restored' => $restorations->sum(fn (array $plan): int => count($plan['fields'])),
            'records_hidden' => $hiding->count(),
            'backup' => $backupPath,
            'by' => get_current_user(),
        ]);

        $this->components->info("Restored {$restorations->count()} record(s) and hid {$hiding->count()}. Backup of the values replaced: storage/app/private/{$backupPath}");

        return self::SUCCESS;
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    private function window(): ?array
    {
        try {
            $from = CarbonImmutable::parse((string) $this->option('from'), 'UTC');
            $to = CarbonImmutable::parse((string) $this->option('to'), 'UTC');
        } catch (Throwable) {
            $from = $to = null;
        }

        if (blank($this->option('from')) || blank($this->option('to')) || $from === null || $to === null || $to->lessThan($from)) {
            $this->components->error('Give the window as --from and --to in UTC, with --to after --from.');

            return null;
        }

        return [$from, $to];
    }

    /**
     * For each record, the value every field had before the window and the
     * value the process left, restoring only fields nobody has changed since.
     *
     * @param  Collection<int, AuditLog>  $logs
     * @return array{0: Collection<int, array{record: Model, fields: array<string, array{now: mixed, restore: mixed}>}>, 1: Collection<int, array{record: string, field: string, reason: string}>, 2: Collection<int, array{record: string, reason: string}>}
     */
    private function planRestorations(Collection $logs): array
    {
        $restorations = collect();
        $kept = collect();
        $skipped = collect();

        foreach ($logs->groupBy(fn (AuditLog $log): string => $log->auditable_type.'#'.$log->auditable_id) as $recordLogs) {
            $first = $recordLogs->first();
            $label = class_basename($first->auditable_type).' #'.$first->auditable_id;
            $record = $this->findRecord($first->auditable_type, $first->auditable_id);

            if ($record === null) {
                $skipped->push(['record' => $label, 'reason' => 'no longer exists']);

                continue;
            }

            $label = $this->label($record);

            if ($this->isDebtConsolidationContent($record)) {
                $skipped->push(['record' => $label, 'reason' => 'Debt Consolidation content is never restored']);

                continue;
            }

            $fields = [];

            foreach ($this->changesAcrossWindow($recordLogs) as $field => $change) {
                $current = $record->getRawOriginal($field);

                if ($this->comparable($current) != $this->comparable($change['new'])) {
                    $kept->push(['record' => $label, 'field' => $field, 'reason' => 'changed again after the window; current value kept']);

                    continue;
                }

                $fields[$field] = ['now' => $current, 'restore' => $change['old']];
            }

            if ($fields !== []) {
                $restorations->push(['record' => $record, 'fields' => $fields]);
            }
        }

        return [$restorations, $kept, $skipped];
    }

    /**
     * Records created in the window and still live. One someone has edited
     * since is left alone and listed, because a person chose to keep it.
     *
     * @param  Collection<int, AuditLog>  $logs
     * @return array{0: Collection<int, Model>, 1: Collection<int, array{record: string, reason: string}>}
     */
    private function planHiding(Collection $logs, CarbonImmutable $to): array
    {
        $toHide = collect();
        $kept = collect();

        foreach ($logs as $log) {
            $record = $this->findRecord($log->auditable_type, $log->auditable_id);

            if ($record === null || ! $this->isLive($record) || $this->isDebtConsolidationContent($record)) {
                continue;
            }

            $editedSince = AuditLog::query()
                ->where('auditable_type', $log->auditable_type)
                ->where('auditable_id', $log->auditable_id)
                ->where('created_at', '>', $to)
                ->whereNotNull('user_id')
                ->exists();

            if ($editedSince) {
                $kept->push(['record' => $this->label($record), 'reason' => 'created in the window but edited by staff since; left live']);

                continue;
            }

            $toHide->push($record);
        }

        return [$toHide, $kept];
    }

    /**
     * The first "old" and the last "new" of every field the window changed.
     *
     * @param  Collection<int, AuditLog>  $recordLogs
     * @return array<string, array{old: mixed, new: mixed}>
     */
    private function changesAcrossWindow(Collection $recordLogs): array
    {
        $changes = [];

        foreach ($recordLogs as $log) {
            foreach ((array) $log->changes as $field => $change) {
                $changes[$field] = [
                    'old' => array_key_exists($field, $changes) ? $changes[$field]['old'] : ($change['old'] ?? null),
                    'new' => $change['new'] ?? null,
                ];
            }
        }

        return $changes;
    }

    /**
     * The audit log keeps "old" in its cast form (arrays, enum values, ISO
     * dates) and "new" in its raw form, and MySQL reformats JSON and decimals;
     * this reduces both to one form so equal values compare equal.
     */
    private function comparable(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->utc()->format('Y-m-d H:i:s');
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
            return (string) (float) $value;
        }

        if (is_string($value) && (str_starts_with($value, '[') || str_starts_with($value, '{'))) {
            $decoded = json_decode($value, true);

            return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
        }

        return $value;
    }

    /**
     * The audit log keeps an old date as ISO-8601 in UTC; the model stores the
     * app's local time, so convert rather than trust the two clocks to agree.
     */
    private function restorable(Model $record, string $field, mixed $value): mixed
    {
        if (is_string($value) && $record->hasCast($field, ['date', 'datetime', 'immutable_date', 'immutable_datetime'])) {
            return CarbonImmutable::parse($value)->setTimezone(config('app.timezone'));
        }

        return $value;
    }

    private function findRecord(string $type, int|string $id): ?Model
    {
        $query = $type::query();

        if (in_array(SoftDeletes::class, class_uses_recursive($type), true)) {
            $query->withTrashed();
        }

        return $query->find($id);
    }

    private function isDebtConsolidationContent(Model $record): bool
    {
        $debtPageSlugs = DebtConsolidationPages::slugs();

        if ($record instanceof Page) {
            return in_array($record->slug, $debtPageSlugs, true);
        }

        return $record instanceof Faq
            && $record->faqable instanceof Page
            && in_array($record->faqable->slug, $debtPageSlugs, true);
    }

    private function isLive(Model $record): bool
    {
        return match (true) {
            $record instanceof Lender, $record instanceof LenderProduct => $record->status === LenderStatus::Active,
            default => $record->getAttribute('status') === PublishStatus::Published,
        };
    }

    private function hide(Model $record): void
    {
        $record->setAttribute('status', ($record instanceof Lender || $record instanceof LenderProduct)
            ? LenderStatus::Inactive
            : PublishStatus::Draft);
    }

    private function label(Model $record): string
    {
        $name = $record->getAttribute('slug') ?? $record->getAttribute('question') ?? $record->getAttribute('customer_name') ?? $record->getKey();

        return class_basename($record).' #'.$record->getKey().' ('.Str::limit((string) $name, 50).')';
    }

    /**
     * @param  Collection<int, array{record: Model, fields: array<string, array{now: mixed, restore: mixed}>}>  $restorations
     * @param  Collection<int, array{record: string, field: string, reason: string}>  $kept
     * @param  Collection<int, array{record: string, reason: string}>  $skippedRecords
     * @param  Collection<int, Model>  $toHide
     * @param  Collection<int, array{record: string, reason: string}>  $createdKept
     * @param  Collection<string, int>  $otherTypes
     */
    private function report(CarbonImmutable $from, CarbonImmutable $to, Collection $restorations, Collection $kept, Collection $skippedRecords, Collection $toHide, Collection $createdKept, Collection $otherTypes): void
    {
        $this->components->info("Unattended changes to website content between {$from->toDateTimeString()} and {$to->toDateTimeString()} UTC");

        $this->line("Fields to restore: {$restorations->sum(fn (array $plan): int => count($plan['fields']))} across {$restorations->count()} record(s)");
        $this->table(['Record', 'Field', 'Now', 'Restores to'], $restorations->flatMap(fn (array $plan): array => collect($plan['fields'])
            ->map(fn (array $values, string $field): array => [$this->label($plan['record']), $field, $this->preview($values['now']), $this->preview($values['restore'])])
            ->values()->all())->all());

        if ($kept->isNotEmpty() || $skippedRecords->isNotEmpty() || $createdKept->isNotEmpty()) {
            $this->line('Left as they are:');
            $this->table(['Record', 'Field', 'Why'], [
                ...$kept->map(fn (array $row): array => [$row['record'], $row['field'], $row['reason']])->all(),
                ...$skippedRecords->map(fn (array $row): array => [$row['record'], '(whole record)', $row['reason']])->all(),
                ...$createdKept->map(fn (array $row): array => [$row['record'], '(whole record)', $row['reason']])->all(),
            ]);
        }

        $this->line("Created in the window and still live: {$toHide->count()}".($this->option('hide-created') ? ' (will be hidden)' : ' (pass --hide-created to hide them)'));
        $this->table(['Record'], $toHide->map(fn (Model $record): array => [$this->label($record)])->all());

        if ($otherTypes->isNotEmpty()) {
            $this->line('Also changed in the window but not website content, so not restored here:');
            $this->table(['Type', 'Audit entries'], $otherTypes->map(fn (int $count, string $type): array => [$type, $count])->values()->all());
        }
    }

    private function preview(mixed $value): string
    {
        $text = is_scalar($value) || $value === null ? (string) $value : (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return Str::limit(trim((string) preg_replace('/\s+/', ' ', strip_tags($text))), 60);
    }

    /**
     * @param  Collection<int, array{record: Model, fields: array<string, array{now: mixed, restore: mixed}>}>  $restorations
     * @param  Collection<int, Model>  $hiding
     */
    private function backUp(Collection $restorations, Collection $hiding): string
    {
        $records = $restorations->pluck('record')->merge($hiding)
            ->map(fn (Model $record): array => ['type' => $record::class, 'id' => $record->getKey(), 'attributes' => $record->getRawOriginal()])
            ->values()->all();

        $path = 'content-restores/before-restore-'.now()->format('Ymd-His').'.json';
        Storage::disk('local')->put($path, (string) json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $path;
    }

    /**
     * Saves through the models, so each restore is itself audited, with
     * machine translation paused so restored English doesn't trigger a
     * Hindi re-translation.
     *
     * @param  Collection<int, array{record: Model, fields: array<string, array{now: mixed, restore: mixed}>}>  $restorations
     * @param  Collection<int, Model>  $hiding
     */
    private function apply(Collection $restorations, Collection $hiding): void
    {
        AutoTranslation::paused(fn () => DB::transaction(function () use ($restorations, $hiding): void {
            foreach ($restorations as $plan) {
                foreach ($plan['fields'] as $field => $values) {
                    $plan['record']->setAttribute($field, $this->restorable($plan['record'], $field, $values['restore']));
                }

                $plan['record']->save();
            }

            foreach ($hiding as $record) {
                $this->hide($record);
                $record->save();
            }
        }));

        ContentCache::invalidate(...ContentCacheSection::cases());
    }
}
