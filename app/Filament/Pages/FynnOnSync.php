<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Concerns\BelongsToAdminModule;
use App\Filament\Schemas\IndiaDateRangeFilter;
use App\Models\AuditLog;
use App\Modules\FynnOn\Enums\FynnOnSyncStatus;
use App\Modules\FynnOn\Services\FynnOnLeadPayload;
use App\Modules\FynnOn\Services\FynnOnSettings;
use App\Modules\FynnOn\Services\FynnOnSync as FynnOnSyncService;
use App\Modules\Leads\Enums\LeadSource;
use App\Modules\Leads\Models\LeadInboxEntry;
use App\Modules\Leads\Services\LeadInbox;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * Leads & Enquiries → FynnOn LMS Sync: is the LMS connected, how many leads
 * were delivered / are waiting / failed (per source), every delivery attempt
 * with its HTTP status and safe error, and resend for anything undelivered.
 *
 * Shows lead references and sources only — never names or numbers — so it
 * exposes nothing beyond delivery state. Open to staff who may update
 * enquiries (the people who work leads).
 */
class FynnOnSync extends Page implements HasTable
{
    use BelongsToAdminModule;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPathRoundedSquare;

    protected static ?string $navigationLabel = 'FynnOn LMS Sync';

    protected static ?string $title = 'FynnOn LMS Sync';

    protected static ?string $slug = 'fynnon-lms-sync';

    protected string $view = 'filament.pages.fynnon-sync';

    public static function canAccess(): bool
    {
        return FynnOnSyncService::canManage();
    }

    public static function getNavigationBadge(): ?string
    {
        try {
            $abandoned = LeadInbox::unrestricted()->where('lms_status', FynnOnSyncStatus::Abandoned->value)->count();
        } catch (Throwable) {
            return null;
        }

        return $abandoned > 0 ? (string) $abandoned : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    /**
     * @return array{configured: bool, host: ?string, signed: bool, alerts: bool, sources: array<int, string>}
     */
    public function connection(): array
    {
        return [
            'configured' => FynnOnSettings::isConfigured(),
            'host' => FynnOnSettings::host(),
            'signed' => FynnOnSettings::signingSecret() !== null,
            'alerts' => FynnOnSettings::alertEmail() !== null,
            'sources' => collect(FynnOnSettings::enabledSources())->map->getLabel()->all(),
        ];
    }

    /**
     * Delivery state per lead source. Counts only — no lead data.
     *
     * @return array{rows: array<int, array{label: string, enabled: bool, total: int, delivered: int, pending: int, failed: int, abandoned: int}>, totals: array{total: int, delivered: int, pending: int, failed: int, abandoned: int}, last: ?string}
     */
    public function stats(): array
    {
        $counts = LeadInbox::unrestricted()
            ->toBase()
            ->selectRaw('source, lms_status, COUNT(*) as aggregate')
            ->groupBy('source', 'lms_status')
            ->get();

        $rows = [];

        foreach (LeadSource::cases() as $source) {
            $forSource = $counts->where('source', $source->value);
            $count = fn (array $statuses): int => (int) $forSource->filter(fn (object $row): bool => in_array($row->lms_status, $statuses, true))->sum('aggregate');

            $rows[] = [
                'label' => $source->getLabel(),
                'enabled' => in_array($source, FynnOnSettings::enabledSources(), true),
                'total' => (int) $forSource->sum('aggregate'),
                'delivered' => $count([FynnOnSyncStatus::Sent->value, FynnOnSyncStatus::Duplicate->value]),
                'pending' => $count([null]),
                'failed' => $count([FynnOnSyncStatus::Failed->value]),
                'abandoned' => $count([FynnOnSyncStatus::Abandoned->value]),
            ];
        }

        $totals = collect(['total', 'delivered', 'pending', 'failed', 'abandoned'])
            ->mapWithKeys(fn (string $key): array => [$key => (int) collect($rows)->sum($key)])
            ->all();

        $last = AuditLog::query()
            ->whereIn('action', [FynnOnSyncStatus::Sent->value, FynnOnSyncStatus::Duplicate->value])
            ->latest('id')
            ->value('created_at');

        return [
            'rows' => $rows,
            'totals' => $totals,
            'last' => $last ? Carbon::parse($last)->timezone(IndiaDateRangeFilter::TIMEZONE)->format('d M Y, h:i A') : null,
        ];
    }

    /**
     * The JSON contract, for the LMS team (no real lead data).
     *
     * @return array<int, string>
     */
    public function payloadFields(): array
    {
        return [
            'schema_version, event (lead.created | lead.reopened), reference (stable per lead, also the Idempotency-Key)',
            'source, source_label, submitted_at, sent_at',
            'contact: name, mobile, email, mobile_verified, mobile_verified_at',
            'loan: product, product_slug, amount (and lender for loan applications)',
            'form: type, placement, channel, message',
            'attribution: page_url, page_path, referrer, utm_source, utm_medium, utm_campaign, utm_term, utm_content',
            'status (+ bureau / otp_purpose where relevant). Never: PAN, date of birth, credit score, report contents, OTP codes.',
        ];
    }

    public function exampleReference(): string
    {
        return 'fynnedge:'.LeadSource::Enquiry->value.':9b2c…';
    }

    public function payloadVersion(): string
    {
        return FynnOnLeadPayload::VERSION;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => AuditLog::query()->whereIn('action', FynnOnSyncStatus::recordedActions()))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('d M Y, h:i:s A')
                    ->timezone(IndiaDateRangeFilter::TIMEZONE)
                    ->sortable(),
                TextColumn::make('source')
                    ->label('Lead')
                    ->state(fn (AuditLog $record): ?string => $this->sourceFor($record)?->getLabel())
                    ->description(fn (AuditLog $record): ?string => $record->changes['reference'] ?? null)
                    ->url(fn (AuditLog $record): ?string => $this->leadUrl($record)),
                TextColumn::make('action')
                    ->label('Result')
                    ->formatStateUsing(fn (string $state): string => FynnOnSyncStatus::fromAction($state)->getLabel())
                    ->color(fn (string $state): string => FynnOnSyncStatus::fromAction($state)->getColor())
                    ->badge(),
                TextColumn::make('changes.event')->label('Event')->placeholder('lead.created'),
                TextColumn::make('changes.attempt')->label('Attempt')->placeholder('—'),
                TextColumn::make('changes.http_status')->label('HTTP')->placeholder('—'),
                TextColumn::make('changes.lms_reference')->label('LMS reference')->placeholder('—')->copyable(),
                TextColumn::make('changes.error')->label('Error')->placeholder('—')->wrap()->limit(120),
                TextColumn::make('changes.duration_ms')->label('Time')->suffix(' ms')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->label('Result')
                    ->options(collect(FynnOnSyncStatus::recordedActions())->mapWithKeys(fn (string $action): array => [$action => FynnOnSyncStatus::from($action)->getLabel()])->all()),
                SelectFilter::make('auditable_type')
                    ->label('Source')
                    ->options(collect(LeadSource::cases())->mapWithKeys(fn (LeadSource $source): array => [$source->morphClass() => $source->getLabel()])->all()),
            ])
            ->recordActions([
                Action::make('resend')
                    ->label('Resend')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('warning')
                    ->visible(fn (AuditLog $record): bool => FynnOnSettings::isConfigured()
                        && in_array($record->action, [FynnOnSyncStatus::Failed->value, FynnOnSyncStatus::Abandoned->value], true)
                        && $this->sourceFor($record) !== null)
                    ->requiresConfirmation()
                    ->action(function (AuditLog $record): void {
                        $queued = FynnOnSyncService::queueManually($this->sourceFor($record), (int) $record->auditable_id);

                        $queued
                            ? Notification::make()->title('Queued for FynnOn LMS')->success()->send()
                            : Notification::make()->title('Could not queue this lead')->body('It no longer exists or is not yet a lead.')->warning()->send();
                    }),
            ])
            ->headerActions([
                Action::make('sendUndelivered')
                    ->label('Send undelivered leads')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->visible(fn (): bool => FynnOnSettings::isConfigured())
                    ->schema([
                        DatePicker::make('since')->label('Received on or after')->required()->default(now(IndiaDateRangeFilter::TIMEZONE)->subDays(7)->toDateString()),
                        Toggle::make('include_abandoned')->label('Also resend leads that failed after every retry')->default(true),
                    ])
                    ->modalDescription('Queues every lead from that date that has not been delivered yet. Each lead keeps the same reference, so the LMS updates rather than duplicates anything it already has.')
                    ->action(function (array $data): void {
                        $statuses = ($data['include_abandoned'] ?? false) ? [FynnOnSyncStatus::Abandoned->value] : [];
                        $queued = 0;

                        LeadInbox::query(FynnOnSettings::enabledSources())
                            ->where(fn (Builder $query) => $query->whereNull('lms_status')->orWhereIn('lms_status', $statuses))
                            ->where('received_at', '>=', Carbon::parse($data['since'], IndiaDateRangeFilter::TIMEZONE)->startOfDay()->utc())
                            ->lazy(200)
                            ->each(function (LeadInboxEntry $entry) use (&$queued): void {
                                if (FynnOnSyncService::queueManually($entry->source, $entry->source_id)) {
                                    $queued++;
                                }
                            });

                        Notification::make()->title("{$queued} lead(s) queued for FynnOn LMS")->success()->send();
                    }),
            ])
            ->emptyStateHeading('No deliveries yet')
            ->emptyStateDescription(FynnOnSettings::isConfigured()
                ? 'Deliveries appear here as soon as the next lead arrives.'
                : 'FynnOn LMS is not connected yet, so nothing has been sent. See "Connection" above.');
    }

    private function sourceFor(AuditLog $log): ?LeadSource
    {
        foreach (LeadSource::cases() as $source) {
            if ($source->morphClass() === $log->auditable_type) {
                return $source;
            }
        }

        return null;
    }

    private function leadUrl(AuditLog $log): ?string
    {
        $source = $this->sourceFor($log);

        if ($source === null || ! $source->isAccessible()) {
            return null;
        }

        $publicId = $source->modelClass()::query()->whereKey($log->auditable_id)->value('public_id');

        return $publicId ? $source->resourceClass()::getUrl($source->recordPage(), ['record' => $publicId]) : null;
    }
}
