@use('App\Filament\Resources\ExperianCreditRequests\ExperianCreditRequestResource')
@use('App\Modules\Experian\Enums\ExperianProduct')
@use('App\Modules\Experian\Enums\ExperianRequestStatus')
@use('App\Modules\Experian\Services\ExperianReportDocument')

@php
    /** @var \App\Modules\Experian\Models\ExperianCreditRequest $record */
    $record = $getRecord();
    $isReport = $record->product === ExperianProduct::Report;
    $isPdf = $record->product === ExperianProduct::Pdf;
    $canSeeReport = (bool) auth()->user()?->can('viewReport', $record);
    $hasBody = $record->status === ExperianRequestStatus::Success && $record->purged_at === null && is_array($record->raw_response);
    $document = $hasBody ? new ExperianReportDocument($record->raw_response) : null;
    $showReport = $document && $isReport && $canSeeReport;
    $isWaiting = in_array($record->status, [ExperianRequestStatus::Pending, ExperianRequestStatus::Processing], true);

    $card = 'rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900';
    $heading = 'text-sm font-semibold text-gray-950 dark:text-white';
    $muted = 'text-xs text-gray-500 dark:text-gray-400';
    $label = 'text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400';
    $value = 'text-sm text-gray-950 dark:text-white';
    $th = 'py-2 pe-4 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400';
    $td = 'py-2 pe-4 align-top text-sm text-gray-950 dark:text-white';

    $header = $document?->header() ?? [];
    $overview = [
        'Customer' => $record->customerName(),
        'PAN' => $record->pan_masked,
        'Mobile' => $record->mobile_masked,
        'What was requested' => $record->product->getLabel(),
        'Status' => $record->status->getLabel(),
        'Request type' => $record->request_type->getLabel(),
        'Requested' => $record->requested_at?->format('d M Y, H:i').' UTC',
        'Completed' => $record->completed_at?->format('d M Y, H:i').' UTC',
        'Report date' => $record->report_date?->format('d M Y'),
        'Experian report number' => $record->provider_report_id,
        'Surepass client ID' => $record->provider_client_id,
        'PDF' => $isPdf ? ($record->hasStoredPdf() ? 'Stored (encrypted) '.$record->pdf_fetched_at?->format('d M Y, H:i').' UTC' : ($record->pdf_error_code ? 'Not available — '.$record->pdf_error_code : 'Not stored')) : null,
        'Reusable until' => $record->isReusable() ? $record->reusableUntil()?->format('d M Y') : null,
        'Requested by' => $record->requestedBy?->name,
        'Channel' => ucfirst($record->channel),
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
        [$record->status === ExperianRequestStatus::Success ? 'Result received' : 'Finished: '.$record->status->getLabel(), $record->completed_at, $record->error_code?->getLabel()],
        ['Reconciled', $record->reconciled_at, $record->reconciledBy?->name.' — '.$record->reconciliation_resolution?->getLabel()],
        ['PDF stored', $record->pdf_fetched_at, null],
        ['Contents purged (retention)', $record->purged_at, null],
    ], fn (array $step) => $step[1] !== null);

    $scoreDetails = $document?->scoreDetails() ?? ['value' => null, 'confidence' => null];
@endphp

<div class="flex flex-col gap-6" @if ($isWaiting) wire:poll.5s="refreshRequest" @endif>
    @if ($isWaiting)
        <div class="rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900 dark:border-sky-800 dark:bg-sky-950 dark:text-sky-100">
            <p class="font-semibold">{{ $record->status === ExperianRequestStatus::Pending ? 'Waiting to be sent to Surepass…' : 'Waiting for Surepass…' }}</p>
            <p class="mt-1">This page refreshes by itself. Results usually arrive within a minute.</p>
        </div>
    @elseif ($record->status === ExperianRequestStatus::ReconciliationRequired)
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100">
            <p class="font-semibold">Reconciliation required — {{ $record->error_code?->getLabel() }}</p>
            <p class="mt-1">{{ $record->error_message }}</p>
            <p class="mt-1">No further Experian {{ strtolower($record->product->getLabel()) }} request can be sent for this PAN until an authorised user reconciles this one against the Surepass dashboard.</p>
        </div>
    @elseif ($record->status === ExperianRequestStatus::Failed)
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900 dark:border-red-800 dark:bg-red-950 dark:text-red-100">
            <p class="font-semibold">Request failed — {{ $record->error_code?->getLabel() ?? 'see history' }}</p>
            <p class="mt-1">{{ $record->error_message }}</p>
            @if ($record->reconciliation_resolution)
                <p class="mt-1">Reconciled: {{ $record->reconciliation_resolution->getLabel() }}. {{ $record->reconciliation_note }}</p>
            @endif
            <p class="mt-1">{{ $record->retryable ? 'Surepass did not process it, so it can be retried without a duplicate charge.' : 'It cannot be retried as-is.' }}</p>
        </div>
    @elseif ($record->status === ExperianRequestStatus::Expired || ($record->status === ExperianRequestStatus::Success && ! $document))
        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
            <p class="font-semibold">The result contents are no longer held.</p>
            <p class="mt-1">They were erased under the retention period. The request record below is kept for audit.</p>
        </div>
    @elseif ($document && $isReport && ! $canSeeReport)
        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
            Your role can see the score and status of this report, not its contents. The full report needs the ViewReport permission for Experian.
        </div>
    @elseif ($isPdf && $record->status === ExperianRequestStatus::Success && ! $record->hasStoredPdf())
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100">
            <p class="font-semibold">The PDF has not been stored.</p>
            <p class="mt-1">{{ $record->pdf_error_code ? 'Downloading it from Surepass\'s link failed (the link may have expired).' : 'It has not been downloaded yet.' }} "Download PDF" tries the link once more; if that fails, request a new PDF.</p>
        </div>
    @elseif ($record->status === ExperianRequestStatus::Success && ! $record->isReusable())
        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
            This result is older than its {{ $record->reuseDays() }}-day reuse period, so a new request for this PAN will fetch a new one.
        </div>
    @endif

    <section class="{{ $card }}">
        <h2 class="{{ $heading }}">Request overview</h2>
        <dl class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($overview as $name => $item)
                @continue($item === null && $name === 'PDF')
                <div>
                    <dt class="{{ $label }}">{{ $name }}</dt>
                    <dd class="{{ $value }} mt-0.5 break-words">{{ filled($item) || $item === 0 ? $item : '—' }}</dd>
                </div>
            @endforeach
        </dl>
        @if ($record->fresh_reason)
            <p class="{{ $muted }} mt-4"><span class="font-medium">Fresh pull reason:</span> {{ $record->fresh_reason }}</p>
        @endif
    </section>

    @if ($document)
        <section class="{{ $card }}">
            <h2 class="{{ $heading }}">Experian score</h2>
            <div class="mt-3 flex flex-wrap items-end gap-x-10 gap-y-3">
                <div>
                    <p class="text-5xl font-semibold text-gray-950 dark:text-white">{{ $record->score ?? '—' }}</p>
                    <p class="{{ $muted }} mt-1">
                        @if ($record->score === null && $document->rawScore() !== null)
                            Experian sent "{{ $document->rawScore() }}", which is not a score (often: not enough credit history).
                        @elseif ($record->score === null)
                            Experian sent no score with this result.
                        @else
                            Experian credit score as reported (Indian scores range 300–900).
                        @endif
                    </p>
                </div>
                @if ($scoreDetails['value'] !== null || $scoreDetails['confidence'] !== null)
                    <dl class="grid grid-cols-2 gap-x-6 gap-y-1">
                        <dt class="{{ $label }}">Bureau score</dt>
                        <dd class="{{ $value }}">{{ $scoreDetails['value'] ?? '—' }}</dd>
                        <dt class="{{ $label }}">Confidence level</dt>
                        <dd class="{{ $value }}">{{ $scoreDetails['confidence'] ?? '—' }}</dd>
                    </dl>
                @endif
            </div>
        </section>
    @endif

    @if ($showReport)
        <section class="{{ $card }}">
            <h2 class="{{ $heading }}">Report header</h2>
            <dl class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach (['Report number' => $header['report_number'] ?? null, 'Report date' => $record->report_date?->format('d M Y') ?? ($header['report_date'] ?? null), 'Report time' => $header['report_time'] ?? null, 'Version' => $header['version'] ?? null, 'Subscriber' => $header['subscriber'] ?? null, 'Enquiry user' => $header['enquiry_username'] ?? null, 'Experian message' => $header['message'] ?? null, 'Exact match' => $document->exactMatch()] as $name => $item)
                    <div>
                        <dt class="{{ $label }}">{{ $name }}</dt>
                        <dd class="{{ $value }} mt-0.5 break-words">{{ $item ?? '—' }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="{{ $card }}">
                <h2 class="{{ $heading }}">Personal information</h2>
                @if ($document->personalInfo() !== [])
                    <dl class="mt-3 grid gap-3 sm:grid-cols-2">
                        @foreach ($document->personalInfo() as $name => $item)
                            <div>
                                <dt class="{{ $label }}">{{ $name }}</dt>
                                <dd class="{{ $value }} mt-0.5">{{ $item }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @else
                    <p class="{{ $muted }} mt-3">No personal information reported.</p>
                @endif
            </section>

            <section class="{{ $card }}">
                <h2 class="{{ $heading }}">Identity information</h2>
                <p class="{{ $muted }}">Numbers are masked here; the raw JSON holds the full values.</p>
                @forelse ($document->identities() as $type => $numbers)
                    <div class="mt-3">
                        <p class="{{ $label }}">{{ $type }}</p>
                        <ul class="mt-1 space-y-1">
                            @foreach ($numbers as $number)
                                <li class="{{ $value }}">{{ $number }}</li>
                            @endforeach
                        </ul>
                    </div>
                @empty
                    <p class="{{ $muted }} mt-3">No identity documents reported.</p>
                @endforelse
            </section>
        </div>

        @if ($document->currentApplication() !== [])
            <section class="{{ $card }}">
                <h2 class="{{ $heading }}">Application Experian scored</h2>
                <dl class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($document->currentApplication() as $name => $item)
                        <div>
                            <dt class="{{ $label }}">{{ $name }}</dt>
                            <dd class="{{ $value }} mt-0.5">{{ $item }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endif

        <section class="{{ $card }}">
            <h2 class="{{ $heading }}">Addresses</h2>
            @if ($document->addresses() !== [])
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full">
                        <thead><tr><th class="{{ $th }}">Address</th><th class="{{ $th }}">State</th><th class="{{ $th }}">PIN</th><th class="{{ $th }}">Reported with</th></tr></thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($document->addresses() as $address)
                                <tr>
                                    <td class="{{ $td }}">{{ $address['address'] }}</td>
                                    <td class="{{ $td }}">{{ $address['state'] ?? '—' }}</td>
                                    <td class="{{ $td }}">{{ $address['postal'] ?? '—' }}</td>
                                    <td class="{{ $td }}">{{ $address['source'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="{{ $muted }} mt-3">No addresses reported.</p>
            @endif
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="{{ $card }}">
                <h2 class="{{ $heading }}">Phone numbers</h2>
                @forelse ($document->phones() as $phone)
                    <p class="{{ $value }} mt-2">{{ $phone['number'] }} <span class="{{ $muted }}">· {{ $phone['type'] }}</span></p>
                @empty
                    <p class="{{ $muted }} mt-3">No phone numbers reported.</p>
                @endforelse
            </section>
            <section class="{{ $card }}">
                <h2 class="{{ $heading }}">Email addresses</h2>
                @forelse ($document->emails() as $email)
                    <p class="{{ $value }} mt-2 break-all">{{ $email }}</p>
                @empty
                    <p class="{{ $muted }} mt-3">No email addresses reported.</p>
                @endforelse
            </section>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="{{ $card }}">
                <h2 class="{{ $heading }}">Accounts summary</h2>
                <p class="{{ $muted }}">Experian's own totals (CAIS summary); "—" means it did not state one.</p>
                @if ($document->accountsSummary() !== [])
                    <dl class="mt-3 grid gap-3 sm:grid-cols-2">
                        @foreach ($document->accountsSummary() as $name => $item)
                            <div>
                                <dt class="{{ $label }}">{{ $name }}</dt>
                                <dd class="{{ $value }} mt-0.5">{{ $item }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @else
                    <p class="{{ $muted }} mt-3">No account summary reported.</p>
                @endif
            </section>
            <section class="{{ $card }}">
                <h2 class="{{ $heading }}">Enquiry summary</h2>
                @if ($document->enquirySummary() !== [])
                    <dl class="mt-3 grid gap-3 sm:grid-cols-2">
                        @foreach ($document->enquirySummary() as $name => $item)
                            <div>
                                <dt class="{{ $label }}">{{ $name }}</dt>
                                <dd class="{{ $value }} mt-0.5">{{ $item }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @else
                    <p class="{{ $muted }} mt-3">No enquiry summary reported.</p>
                @endif
            </section>
        </div>

        <section class="{{ $card }}">
            <h2 class="{{ $heading }}">Accounts ({{ count($document->accounts()) }})</h2>
            <div class="mt-3 flex flex-col gap-2">
                @forelse ($document->accounts() as $account)
                    <details class="rounded-lg border border-gray-200 dark:border-gray-700">
                        <summary class="flex cursor-pointer flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3 text-sm">
                            <span class="font-medium text-gray-950 dark:text-white">{{ $account['fields']['Lender'] ?? 'Unknown lender' }}</span>
                            <span class="text-gray-600 dark:text-gray-300">{{ $account['fields']['Account type'] ?? 'Account' }}</span>
                            <span class="rounded-md bg-gray-100 px-2 py-0.5 text-xs text-gray-700 dark:bg-gray-800 dark:text-gray-200">{{ $account['fields']['Open / closed'] }}</span>
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
                            @if ($account['history'] !== [])
                                <p class="{{ $label }} mt-4">Monthly payment history (month — days past due / asset class)</p>
                                <div class="mt-1 flex flex-wrap gap-1.5">
                                    @foreach ($account['history'] as $month)
                                        <span @class([
                                            'rounded-md px-2 py-0.5 font-mono text-xs',
                                            'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200' => $month['late'],
                                            'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200' => ! $month['late'],
                                        ])>{{ $month['month'] }} {{ $month['status'] }}</span>
                                    @endforeach
                                </div>
                            @endif
                            @if ($account['extra'] !== [])
                                <p class="{{ $label }} mt-4">Other fields reported for this account (identifiers masked)</p>
                                <div class="mt-1">@include('filament.experian.partials.value', ['value' => $account['extra']])</div>
                            @endif
                        </div>
                    </details>
                @empty
                    <p class="{{ $muted }}">Experian reported no accounts.</p>
                @endforelse
            </div>
        </section>

        @foreach (['Credit enquiries' => $document->enquiries(), 'Non-credit enquiries' => $document->nonCreditEnquiries()] as $title => $enquiries)
            <section class="{{ $card }}">
                <h2 class="{{ $heading }}">{{ $title }}</h2>
                @if ($enquiries !== [])
                    <div class="mt-3 overflow-x-auto">
                        <table class="w-full">
                            <thead><tr>@foreach (array_keys($enquiries[0]['fields']) as $name)<th class="{{ $th }}">{{ $name }}</th>@endforeach</tr></thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach ($enquiries as $enquiry)
                                    <tr>
                                        @foreach ($enquiry['fields'] as $item)
                                            <td class="{{ $td }}">{{ $item ?? '—' }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="{{ $muted }} mt-3">None listed in the report.</p>
                @endif
            </section>
        @endforeach

        @if ($document->otherSections() !== [])
            <section class="{{ $card }}">
                <h2 class="{{ $heading }}">Other report sections</h2>
                <p class="{{ $muted }}">Sections without a dedicated layout, shown as received (identifiers masked).</p>
                <div class="mt-3">@include('filament.experian.partials.value', ['value' => $document->otherSections()])</div>
            </section>
        @endif

        @if ($document->providerFields() !== [])
            <section class="{{ $card }}">
                <h2 class="{{ $heading }}">Surepass response fields</h2>
                <div class="mt-3">@include('filament.experian.partials.value', ['value' => $document->providerFields()])</div>
            </section>
        @endif
    @endif

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
        @if ($record->previousRequest)
            <p class="{{ $muted }} mt-1">Follows attempt <a class="underline" href="{{ ExperianCreditRequestResource::getUrl('view', ['record' => $record->previousRequest]) }}">{{ $record->previousRequest->requested_at?->format('d M Y, H:i') }}</a>.</p>
        @endif
        <p class="{{ $muted }} mt-1">Who viewed or acted on this request is listed under History below, and across all requests in Experian Audit History (both need the View:AuditLog permission).</p>
    </section>
</div>
