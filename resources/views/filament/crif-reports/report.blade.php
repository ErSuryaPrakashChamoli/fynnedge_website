@use('App\Filament\Resources\CrifReports\CrifReportResource')
@use('App\Modules\CreditBureau\Enums\CrifReportStatus')
@use('App\Modules\CreditBureau\Services\CrifReportDocument')
@use('App\Modules\CreditBureau\Services\CrifSettings')

@php
    /** @var \App\Modules\CreditBureau\Models\CrifReport $record */
    $record = $getRecord();
    $document = $record->hasReport() ? new CrifReportDocument($record->raw_response) : null;
    $isWaiting = in_array($record->status, [CrifReportStatus::Pending, CrifReportStatus::Processing], true);

    $card = 'rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900';
    $heading = 'text-sm font-semibold text-gray-950 dark:text-white';
    $muted = 'text-xs text-gray-500 dark:text-gray-400';
    $label = 'text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400';
    $value = 'text-sm text-gray-950 dark:text-white';

    $overview = [
        'Customer' => $record->customerName(),
        'PAN' => $record->pan_masked,
        'Mobile' => $record->mobile_masked,
        'Credit score' => $record->score ?? ($document?->rawScore() !== null ? 'Not a valid score ('.$document->rawScore().')' : null),
        'Provider' => 'CRIF High Mark via Surepass',
        'Status' => $record->status->getLabel(),
        'Request type' => $record->request_type->getLabel(),
        'Requested' => $record->requested_at?->format('d M Y, H:i').' UTC',
        'Report generated' => $record->report_issued_on?->format('d M Y'),
        'Report ID' => $record->provider_report_id,
        'Inquiry ID' => $record->provider_inquiry_id,
        'Surepass client ID' => $record->provider_client_id,
        'Fetched' => $record->status === CrifReportStatus::Success ? $record->completed_at?->format('d M Y, H:i').' UTC' : null,
        'Reusable until' => $record->isReusable() ? $record->reusableUntil()?->format('d M Y') : null,
        'Initiated by' => $record->requestedBy?->name,
        'Linked application' => $record->journeySession?->public_id,
        'Consent' => $record->consent
            ? ($record->consent->method?->getLabel() ?? 'Website application').' — '.$record->consent->consented_at?->format('d M Y, H:i').' UTC'.(filled($record->consent->evidence_reference) ? ' (ref. '.$record->consent->evidence_reference.')' : '').' · '.$record->consent->status->getLabel()
            : null,
        'Times reused' => $record->times_reused,
        'Duplicate requests blocked' => $record->duplicates_blocked,
    ];

    $timeline = array_filter([
        ['Requested', $record->requested_at, $record->requestedBy?->name],
        ['Sent to Surepass', $record->processing_started_at, null],
        [$record->status === CrifReportStatus::Success ? 'Report received' : 'Finished: '.$record->status->getLabel(), $record->completed_at, $record->error_code?->getLabel()],
        ['Reconciled', $record->reconciled_at, $record->reconciledBy?->name.' — '.$record->reconciliation_resolution?->getLabel()],
        ['PDF stored', $record->pdf_fetched_at, null],
        ['Contents purged (retention)', $record->purged_at, null],
    ], fn (array $step) => $step[1] !== null);
@endphp

<div class="flex flex-col gap-6" @if ($isWaiting) wire:poll.5s="refreshReport" @endif>
    {{-- Status banner for anything other than a usable report. --}}
    @if ($isWaiting)
        <div class="rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900 dark:border-sky-800 dark:bg-sky-950 dark:text-sky-100">
            <p class="font-semibold">{{ $record->status === CrifReportStatus::Pending ? 'Waiting to be sent to Surepass…' : 'Waiting for Surepass…' }}</p>
            <p class="mt-1">This page refreshes by itself. Reports usually arrive within a minute.</p>
        </div>
    @elseif ($record->status === CrifReportStatus::ReconciliationRequired)
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100">
            <p class="font-semibold">Reconciliation required — {{ $record->error_code?->getLabel() }}</p>
            <p class="mt-1">{{ $record->error_message }}</p>
            <p class="mt-1">No further CRIF request can be sent for this PAN until an authorised user reconciles this one against the Surepass dashboard.</p>
        </div>
    @elseif ($record->status === CrifReportStatus::Failed)
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900 dark:border-red-800 dark:bg-red-950 dark:text-red-100">
            <p class="font-semibold">Request failed — {{ $record->error_code?->getLabel() ?? 'see history' }}</p>
            <p class="mt-1">{{ $record->error_message }}</p>
            @if ($record->reconciliation_resolution)
                <p class="mt-1">Reconciled: {{ $record->reconciliation_resolution->getLabel() }}. {{ $record->reconciliation_note }}</p>
            @endif
            <p class="mt-1">{{ $record->retryable ? 'Surepass did not process it, so it can be retried without a duplicate charge.' : 'It cannot be retried as-is.' }}</p>
        </div>
    @elseif ($record->status === CrifReportStatus::Expired || ($record->status === CrifReportStatus::Success && ! $document))
        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
            <p class="font-semibold">The report contents are no longer held.</p>
            <p class="mt-1">They were erased under the retention period. The request record below is kept for audit.</p>
        </div>
    @elseif ($record->status === CrifReportStatus::Success && ! $record->isReusable())
        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
            This report is older than the {{ CrifSettings::reuseDays() }}-day reuse period, so a new request for this PAN will fetch a new report.
        </div>
    @endif

    {{-- A. Overview --}}
    <section class="{{ $card }}">
        <h2 class="{{ $heading }}">Report overview</h2>
        <dl class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($overview as $name => $item)
                <div>
                    <dt class="{{ $label }}">{{ $name }}</dt>
                    <dd class="{{ $value }} mt-0.5 break-words">{{ filled($item) || $item === 0 ? $item : '—' }}</dd>
                </div>
            @endforeach
        </dl>
        @if ($record->fresh_reason)
            <p class="{{ $muted }} mt-4"><span class="font-medium">Fresh report reason:</span> {{ $record->fresh_reason }}</p>
        @endif
    </section>

    @if ($document)
        <div class="grid gap-6 lg:grid-cols-3">
            {{-- F. Score --}}
            <section class="{{ $card }}">
                <h2 class="{{ $heading }}">Score</h2>
                <p class="mt-3 text-5xl font-semibold text-gray-950 dark:text-white">{{ $record->score ?? '—' }}</p>
                <p class="{{ $muted }} mt-1">CRIF scores range from 300 to 900.</p>
                @forelse ($document->scores() as $score)
                    <div class="mt-4 border-t border-gray-100 pt-3 dark:border-gray-800">
                        <p class="{{ $value }}"><span class="font-medium">{{ $score['type'] ?? 'Score' }}</span>: {{ $score['value'] ?? '—' }}</p>
                        @if ($score['comments'])
                            <p class="{{ $muted }} mt-1">{{ $score['comments'] }}</p>
                        @endif
                        @if ($score['factors'] !== [])
                            <p class="{{ $label }} mt-2">Score factors (CRIF reason codes)</p>
                            <div class="mt-1 flex flex-wrap gap-1.5">
                                @foreach ($score['factors'] as $factor)
                                    <span class="rounded-md bg-gray-100 px-2 py-0.5 font-mono text-xs text-gray-700 dark:bg-gray-800 dark:text-gray-200">{{ $factor }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="{{ $muted }} mt-4">No score details in the report.</p>
                @endforelse
            </section>

            {{-- B. Credit summary --}}
            <section class="{{ $card }} lg:col-span-2">
                <h2 class="{{ $heading }}">Credit summary</h2>
                <p class="{{ $muted }}">Bureau figures as reported by CRIF; "—" means CRIF did not supply that figure.</p>
                <dl class="mt-3 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($document->summary() as $name => $item)
                        <div>
                            <dt class="{{ $label }}">{{ $name }}</dt>
                            <dd class="{{ $value }} mt-0.5">{{ $item ?? '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
                @if ($document->secondarySummary() !== [])
                    <h3 class="{{ $heading }} mt-5">Secondary accounts (guarantor / joint)</h3>
                    <dl class="mt-2 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($document->secondarySummary() as $name => $item)
                            <div>
                                <dt class="{{ $label }}">{{ $name }}</dt>
                                <dd class="{{ $value }} mt-0.5">{{ $item ?? '—' }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </section>
        </div>

        {{-- C. Accounts --}}
        <section class="{{ $card }}">
            <h2 class="{{ $heading }}">Accounts ({{ count($document->accounts()) }})</h2>
            <div class="mt-3 flex flex-col gap-2">
                @forelse ($document->accounts() as $index => $account)
                    <details class="rounded-lg border border-gray-200 dark:border-gray-700">
                        <summary class="flex cursor-pointer flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3 text-sm">
                            <span class="font-medium text-gray-950 dark:text-white">{{ $account['fields']['Lender'] ?? 'Unknown lender' }}</span>
                            <span class="text-gray-600 dark:text-gray-300">{{ $account['fields']['Account type'] ?? 'Account' }}</span>
                            <span class="rounded-md bg-gray-100 px-2 py-0.5 text-xs text-gray-700 dark:bg-gray-800 dark:text-gray-200">{{ $account['fields']['Status'] ?? 'Status not reported' }}</span>
                            <span class="ms-auto text-gray-600 dark:text-gray-300">Balance {{ $account['fields']['Current balance'] ?? '—' }}</span>
                        </summary>
                        <div class="border-t border-gray-200 px-4 py-3 dark:border-gray-700">
                            <dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                @foreach ($account['fields'] as $name => $item)
                                    <div>
                                        <dt class="{{ $label }}">{{ $name }}</dt>
                                        <dd class="{{ $value }} mt-0.5 break-words">{{ $item ?? '—' }}</dd>
                                    </div>
                                @endforeach
                            </dl>

                            @if ($account['payment_history'] !== [])
                                <p class="{{ $label }} mt-4">Payment history (newest first — days past due / asset class)</p>
                                <div class="mt-1 flex flex-wrap gap-1.5">
                                    @foreach ($account['payment_history'] as $month)
                                        <span @class([
                                            'rounded-md px-2 py-0.5 font-mono text-xs',
                                            'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200' => $month['late'],
                                            'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200' => ! $month['late'],
                                        ])>{{ $month['month'] }} {{ $month['value'] }}</span>
                                    @endforeach
                                </div>
                            @endif

                            @if ($account['extra'] !== [])
                                <p class="{{ $label }} mt-4">Other fields reported for this account</p>
                                <div class="mt-1">
                                    @include('filament.crif-reports.partials.value', ['value' => $account['extra']])
                                </div>
                            @endif
                        </div>
                    </details>
                @empty
                    <p class="{{ $muted }}">CRIF reported no accounts.</p>
                @endforelse
            </div>
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            {{-- D. Personal information variations --}}
            <section class="{{ $card }}">
                <h2 class="{{ $heading }}">Personal information variations</h2>
                <p class="{{ $muted }}">Identity numbers and phone numbers are masked here; the raw JSON holds the full values.</p>
                @forelse ($document->variations() as $type => $rows)
                    <div class="mt-3">
                        <p class="{{ $label }}">{{ $type }}</p>
                        <ul class="mt-1 space-y-1">
                            @foreach ($rows as $row)
                                <li class="{{ $value }}">{{ $row['value'] }} <span class="{{ $muted }}">{{ $row['reported_on'] ? '· reported '.$row['reported_on'] : '' }}</span></li>
                            @endforeach
                        </ul>
                    </div>
                @empty
                    <p class="{{ $muted }} mt-3">No variations reported.</p>
                @endforelse
            </section>

            {{-- E. Inquiry history --}}
            <section class="{{ $card }}">
                <h2 class="{{ $heading }}">Inquiry history</h2>
                @forelse ($document->inquiries() as $inquiry)
                    <div class="mt-3 border-t border-gray-100 pt-3 first:border-0 first:pt-0 dark:border-gray-800">
                        <dl class="grid gap-2 sm:grid-cols-3">
                            @foreach ($inquiry['fields'] as $name => $item)
                                <div>
                                    <dt class="{{ $label }}">{{ $name }}</dt>
                                    <dd class="{{ $value }} mt-0.5">{{ $item ?? '—' }}</dd>
                                </div>
                            @endforeach
                        </dl>
                        @if ($inquiry['extra'] !== [])
                            <div class="mt-2">@include('filament.crif-reports.partials.value', ['value' => $inquiry['extra']])</div>
                        @endif
                    </div>
                @empty
                    <p class="{{ $muted }} mt-3">No inquiries listed in the report.</p>
                @endforelse
            </section>
        </div>

        @if ($document->employment() !== [])
            <section class="{{ $card }}">
                <h2 class="{{ $heading }}">Employment details</h2>
                <div class="mt-3">@include('filament.crif-reports.partials.value', ['value' => $document->employment()])</div>
            </section>
        @endif

        {{-- Everything else CRIF / Surepass sent, so nothing is lost. --}}
        @if ($document->otherSections() !== [])
            <section class="{{ $card }}">
                <h2 class="{{ $heading }}">Other report sections</h2>
                <p class="{{ $muted }}">Sections without a dedicated layout, shown as received.</p>
                <div class="mt-3">@include('filament.crif-reports.partials.value', ['value' => $document->otherSections()])</div>
            </section>
        @endif

        @if ($document->providerFields() !== [])
            <section class="{{ $card }}">
                <h2 class="{{ $heading }}">Surepass response fields</h2>
                <div class="mt-3">@include('filament.crif-reports.partials.value', ['value' => $document->providerFields()])</div>
            </section>
        @endif
    @endif

    {{-- Processing history --}}
    <section class="{{ $card }}">
        <h2 class="{{ $heading }}">Processing history</h2>
        <ol class="mt-3 space-y-2">
            @foreach ($timeline as [$step, $at, $detail])
                <li class="flex flex-wrap gap-x-3 text-sm">
                    <span class="w-40 shrink-0 text-gray-500 dark:text-gray-400">{{ $at->format('d M Y, H:i:s') }}</span>
                    <span class="font-medium text-gray-950 dark:text-white">{{ $step }}</span>
                    @if (filled(trim((string) $detail, ' —')))
                        <span class="text-gray-600 dark:text-gray-300">{{ trim((string) $detail, ' —') }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
        @if ($record->http_status || $record->provider_message_code)
            <p class="{{ $muted }} mt-3">Provider HTTP status {{ $record->http_status ?? '—' }} · message code {{ $record->provider_message_code ?? '—' }}</p>
        @endif
        @if ($record->previousReport)
            <p class="{{ $muted }} mt-1">Follows attempt <a class="underline" href="{{ CrifReportResource::getUrl('view', ['record' => $record->previousReport]) }}">{{ $record->previousReport->requested_at?->format('d M Y, H:i') }}</a>.</p>
        @endif
        <p class="{{ $muted }} mt-1">Who viewed or acted on this report is listed under History below (needs the View:AuditLog permission).</p>
    </section>
</div>
