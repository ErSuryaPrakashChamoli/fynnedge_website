<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Concerns\BelongsToAdminModule;
use App\Filament\Schemas\IndiaDateRangeFilter;
use App\Models\LoanProduct;
use App\Modules\FynnOn\Enums\FynnOnSyncStatus;
use App\Modules\FynnOn\Services\FynnOnSync as FynnOnSyncService;
use App\Modules\Leads\Enums\LeadSource;
use App\Modules\Leads\Models\LeadInboxEntry;
use App\Modules\Leads\Services\LeadInbox as LeadInboxQuery;
use App\Modules\Leads\Services\LeadsCsv;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Leads & Enquiries → Lead Inbox: every lead from every website form in one
 * table — enquiries, credit score checks, online and loan applications, and
 * OTP verifications — with source page, campaign, verification and FynnOn LMS
 * delivery. Read-only: each row opens its own record, where the lead is worked.
 *
 * A user sees only the sources whose own screens they can open, and the page
 * itself only if they can open at least one.
 */
class LeadInbox extends Page implements HasTable
{
    use BelongsToAdminModule;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static ?string $navigationLabel = 'Lead Inbox';

    protected static ?string $title = 'Lead Inbox';

    protected static ?string $slug = 'lead-inbox';

    protected string $view = 'filament.pages.lead-inbox';

    public static function canAccess(): bool
    {
        return LeadSource::accessible() !== [];
    }

    public function table(Table $table): Table
    {
        $sources = LeadSource::accessible();
        $defaultSources = collect($sources)->reject(fn (LeadSource $source): bool => $source === LeadSource::OtpVerification)->map->value->values()->all();

        return $table
            ->query(fn (): Builder => LeadInboxQuery::query($sources)->with('loanProduct'))
            ->defaultSort('received_at', 'desc')
            ->recordUrl(fn (LeadInboxEntry $record): ?string => $record->recordUrl())
            ->columns([
                TextColumn::make('received_at')
                    ->label('Received')
                    ->dateTime('d M Y, h:i A')
                    ->timezone(IndiaDateRangeFilter::TIMEZONE)
                    ->description(fn (LeadInboxEntry $record): ?string => $record->received_at?->diffForHumans())
                    ->sortable(),
                TextColumn::make('source')
                    ->badge()
                    ->description(fn (LeadInboxEntry $record): ?string => $record->detailLabel()),
                TextColumn::make('name')
                    ->placeholder('Not given')
                    ->description(fn (LeadInboxEntry $record): ?string => $record->email)
                    ->searchable(['name', 'email']),
                TextColumn::make('phone')
                    ->label('Mobile')
                    ->placeholder('—')
                    ->copyable()
                    ->searchable(),
                IconColumn::make('verified')
                    ->label('Verified')
                    ->state(fn (LeadInboxEntry $record): bool => $record->verified_at !== null)
                    ->boolean()
                    ->tooltip(fn (LeadInboxEntry $record): string => $record->verified_at ? 'Mobile verified by OTP' : 'Mobile not verified'),
                TextColumn::make('lms_status')
                    ->label('FynnOn LMS')
                    ->state(fn (LeadInboxEntry $record): FynnOnSyncStatus => $record->syncStatus())
                    ->badge()
                    ->description(fn (LeadInboxEntry $record): ?string => $record->lms_at?->timezone(IndiaDateRangeFilter::TIMEZONE)->format('d M, h:i A')),
                TextColumn::make('loanProduct.name')
                    ->label('Product')
                    ->placeholder('General'),
                TextColumn::make('source_page')
                    ->label('Source page')
                    ->state(fn (LeadInboxEntry $record): ?string => $record->sourcePath())
                    ->placeholder('Not recorded')
                    ->limit(36)
                    ->tooltip(fn (LeadInboxEntry $record): ?string => $record->source_page)
                    ->searchable(),
                TextColumn::make('utm_campaign')
                    ->label('Campaign')
                    ->placeholder('—')
                    ->description(fn (LeadInboxEntry $record): ?string => collect([$record->utm_source, $record->utm_medium])->filter()->implode(' / ') ?: null)
                    ->toggleable(),
                TextColumn::make('status')
                    ->state(fn (LeadInboxEntry $record): ?string => $record->statusLabel())
                    ->badge()
                    ->color('gray'),
                TextColumn::make('repeat_count')
                    ->label('Repeats')
                    ->tooltip('Times this number submitted again for the same product (merged into one enquiry, not duplicated).')
                    ->formatStateUsing(fn (?int $state): string => $state !== null && $state > 1 ? "×{$state}" : '—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('source')
                    ->label('Source')
                    ->multiple()
                    ->options(collect($sources)->mapWithKeys(fn (LeadSource $source): array => [$source->value => $source->getLabel()])->all())
                    ->default($defaultSources),
                IndiaDateRangeFilter::make('received_at', 'Received'),
                SelectFilter::make('loan_product_id')
                    ->label('Loan product')
                    ->options(fn (): array => LoanProduct::query()->orderBy('name')->pluck('name', 'id')->all()),
                TernaryFilter::make('verified')
                    ->label('Mobile verified')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('verified_at'),
                        false: fn (Builder $query): Builder => $query->whereNull('verified_at'),
                    ),
                SelectFilter::make('lms_status')
                    ->label('FynnOn LMS')
                    ->options(collect(FynnOnSyncStatus::cases())->mapWithKeys(fn (FynnOnSyncStatus $status): array => [$status->value => $status->getLabel()])->all())
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        null, '' => $query,
                        FynnOnSyncStatus::Pending->value => $query->whereNull('lms_status'),
                        default => $query->where('lms_status', $data['value']),
                    }),
                Filter::make('source_page')
                    ->label('Source page contains')
                    ->schema([TextInput::make('path')->label('Source page contains')->placeholder('/loans/personal-loan')])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['path'] ?? null),
                        fn (Builder $query): Builder => $query->where('source_page', 'like', '%'.addcslashes((string) $data['path'], '%_').'%'),
                    ))
                    ->indicateUsing(fn (array $data): ?string => filled($data['path'] ?? null) ? 'Page contains "'.$data['path'].'"' : null),
                TernaryFilter::make('campaign')
                    ->label('Came from a campaign (UTM)')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('utm_campaign')->orWhereNotNull('utm_source'),
                        false: fn (Builder $query): Builder => $query->whereNull('utm_campaign')->whereNull('utm_source'),
                    ),
                TernaryFilter::make('repeat')
                    ->label('Repeat enquiries')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->where('repeat_count', '>', 1),
                        false: fn (Builder $query): Builder => $query->where('repeat_count', '<=', 1),
                    ),
            ])
            ->filtersFormColumns(2)
            ->recordActions([
                Action::make('journey')
                    ->label('View journey')
                    ->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->color('gray')
                    ->visible(fn (LeadInboxEntry $record): bool => filled($record->phone))
                    ->modalHeading(fn (LeadInboxEntry $record): string => "Everything from {$record->phone}")
                    ->modalDescription('Every record with exactly this mobile number, oldest first, from the sources you can see. Records are linked only by an exact mobile-number match.')
                    ->modalContent(fn (LeadInboxEntry $record): View => view('filament.leads.journey', ['entries' => $this->journeyFor((string) $record->phone)]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                Action::make('resendToLms')
                    ->label('Send to LMS')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('warning')
                    ->visible(fn (LeadInboxEntry $record): bool => FynnOnSyncService::canResend($record))
                    ->requiresConfirmation()
                    ->modalDescription('Queues this lead for delivery to FynnOn LMS now. The LMS receives the same reference as before, so it updates rather than duplicates the lead.')
                    ->action(function (LeadInboxEntry $record): void {
                        FynnOnSyncService::queueManually($record->source, $record->source_id);

                        Notification::make()->title('Queued for FynnOn LMS')->body('The status updates once the LMS replies.')->success()->send();
                    }),
            ])
            ->headerActions([
                Action::make('downloadCsv')
                    ->label('Download CSV')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->action(fn (): StreamedResponse => LeadsCsv::download($this->getFilteredSortedTableQuery() ?? LeadInboxQuery::query($sources))),
            ])
            ->emptyStateHeading('No leads match these filters')
            ->emptyStateDescription('Leads appear here automatically when visitors submit a form on the website.')
            ->paginated([25, 50, 100]);
    }

    /**
     * @return Collection<int, LeadInboxEntry>
     */
    public function journeyFor(string $phone): Collection
    {
        return LeadInboxQuery::query()
            ->with('loanProduct')
            ->where('phone', $phone)
            ->orderBy('received_at')
            ->get();
    }

    /**
     * Leads received today and in the last 7 days per source, for the summary strip.
     *
     * @return array<int, array{label: string, color: string, today: int, week: int}>
     */
    public function summary(): array
    {
        $since = now(IndiaDateRangeFilter::TIMEZONE)->subDays(6)->startOfDay()->utc();
        $today = now(IndiaDateRangeFilter::TIMEZONE)->startOfDay()->utc();

        $counts = LeadInboxQuery::query()
            ->where('received_at', '>=', $since)
            ->toBase()
            ->selectRaw('source, SUM(CASE WHEN received_at >= ? THEN 1 ELSE 0 END) as today, COUNT(*) as week', [$today])
            ->groupBy('source')
            ->get()
            ->keyBy('source');

        return collect(LeadSource::accessible())
            ->map(fn (LeadSource $source): array => [
                'label' => $source->getLabel(),
                'color' => $source->getColor(),
                'today' => (int) ($counts[$source->value]->today ?? 0),
                'week' => (int) ($counts[$source->value]->week ?? 0),
            ])
            ->all();
    }
}
