<x-layouts.portal :title="__(':bureau credit report', ['bureau' => $report->sourceBureau()->getLabel()])" :customer="$customer">
    <a href="{{ route('portal.reports.index') }}" class="text-sm font-medium text-accent underline">{{ __('← All my reports') }}</a>

    {{-- Headline --}}
    <x-ui.card class="mt-4">
        <div class="flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.badge tone="accent">{{ $report->sourceBureau()->getLabel() }}</x-ui.badge>
                    <x-portal.check-status :check="$check" />
                </div>
                <dl class="mt-4 grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                    <div>
                        <dt class="text-ink-faint">{{ __('Report date') }}</dt>
                        <dd class="text-ink">{{ $report->reportDate()?->translatedFormat('d M Y') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-ink-faint">{{ __('Requested on') }}</dt>
                        <dd class="text-ink">{{ $check->requested_at?->translatedFormat('d M Y') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-ink-faint">{{ __('PAN') }}</dt>
                        <dd class="font-mono text-ink">{{ \App\Modules\CreditBureau\Services\PanNumber::mask((string) $check->pan_number) }}</dd>
                    </div>
                    <div>
                        <dt class="text-ink-faint">{{ __('Name checked') }}</dt>
                        <dd class="text-ink">{{ $check->full_name }}</dd>
                    </div>
                </dl>
            </div>
            @if ($check->score !== null)
                <div class="flex shrink-0 flex-col items-center">
                    <x-site.credit-score-meter :score="$check->score" />
                    <p class="mt-2 text-xs text-ink-faint">{{ __(':bureau scores range from 300 to 900.', ['bureau' => $report->sourceBureau()->getLabel()]) }}</p>
                </div>
            @endif
        </div>

        @if ($report->isStandIn())
            <x-ui.alert class="mt-5">{{ __('This report is from :source, fetched on the :bureau page — it is not a :bureau report. Scores can differ a little between bureaus.', ['source' => $report->sourceBureau()->getLabel(), 'bureau' => $check->bureau->getLabel()]) }}</x-ui.alert>
        @endif

        @if ($check->status === \App\Modules\CreditBureau\Enums\CreditCheckStatus::Failed)
            <x-ui.alert tone="warn" class="mt-5">{{ __('The bureau could not return a report for this check. Your earlier reports are not affected.') }}</x-ui.alert>
        @elseif ($check->status === \App\Modules\CreditBureau\Enums\CreditCheckStatus::Pending)
            <x-ui.alert tone="warn" class="mt-5">{{ __('We have not yet received this result from the bureau. Please check back later — we will not ask the bureau again until this one is settled.') }}</x-ui.alert>
        @elseif (! $report->hasFullReport())
            <x-ui.alert class="mt-5">{{ __('Only the score from this check was saved — the detailed report is not available to view.') }}</x-ui.alert>
        @endif

        @if ($report->hasPdf())
            <x-ui.button tag="a" :href="route('portal.reports.pdf', $check->public_id)" target="_blank" rel="noopener" variant="secondary" size="sm" class="mt-5">{{ __('Open PDF report') }}</x-ui.button>
        @endif
    </x-ui.card>

    @if ($report->hasFullReport())
        <p class="mt-6 text-xs text-ink-faint">{{ __('The details below are as reported by :bureau. They may differ from your profile with us; if something here is wrong, it has to be corrected with the bureau or the lender that reported it.', ['bureau' => $report->sourceBureau()->getLabel()]) }}</p>

        @php
            $personal = $report->personalInfo();
            $identities = $report->identities();
            $addresses = $report->addresses();
            $accountsSummary = $report->accountsSummary();
            $accounts = $report->accounts();
            $enquirySummary = $report->enquirySummary();
            $enquiries = $report->enquiries();
        @endphp

        @if ($personal !== [] || $identities !== [] || $addresses !== [])
            <x-ui.card class="mt-4" data-report-section="personal">
                <h2 class="font-display text-lg font-semibold text-ink">{{ __('Personal information reported') }}</h2>
                @if ($personal !== [])
                    <dl class="mt-4 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                        @foreach ($personal as $label => $value)
                            <div>
                                <dt class="text-ink-faint">{{ __($label) }}</dt>
                                <dd class="text-ink">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
                @if ($identities !== [])
                    <p class="mt-5 text-sm font-medium text-ink">{{ __('Identity documents (masked)') }}</p>
                    <ul class="mt-2 flex flex-col gap-1 text-sm text-ink-muted">
                        @foreach ($identities as $type => $numbers)
                            <li>{{ __($type) }}: <span class="font-mono">{{ implode(', ', $numbers) }}</span></li>
                        @endforeach
                    </ul>
                @endif
                @if ($addresses !== [])
                    <p class="mt-5 text-sm font-medium text-ink">{{ __('Addresses') }}</p>
                    <ul class="mt-2 flex flex-col gap-1.5 text-sm text-ink-muted">
                        @foreach ($addresses as $address)
                            <li>{{ $address }}</li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        @endif

        @if ($accountsSummary !== [])
            <x-ui.card class="mt-4" data-report-section="summary">
                <h2 class="font-display text-lg font-semibold text-ink">{{ __('Account summary') }}</h2>
                <dl class="mt-4 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($accountsSummary as $label => $value)
                        <div>
                            <dt class="text-ink-faint">{{ __($label) }}</dt>
                            <dd class="text-ink">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-ui.card>
        @endif

        <x-ui.card class="mt-4" data-report-section="accounts">
            <h2 class="font-display text-lg font-semibold text-ink">{{ __('Credit accounts') }} <span class="text-sm font-normal text-ink-faint">({{ count($accounts) }})</span></h2>
            @forelse ($accounts as $account)
                <details class="mt-3 rounded-xl border border-line" @if ($loop->first) open @endif>
                    <summary class="flex cursor-pointer flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm">
                        <span class="font-medium text-ink">{{ $account['fields']['Lender'] ?? $account['fields']['Member name'] ?? $account['fields']['Account type'] ?? __('Account :number', ['number' => $loop->iteration]) }}</span>
                        <span class="text-xs text-ink-faint">{{ $account['fields']['Account type'] ?? '' }} {{ isset($account['fields']['Open / closed']) ? '· '.__($account['fields']['Open / closed']) : '' }}</span>
                    </summary>
                    <div class="border-t border-line px-4 py-4">
                        <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($account['fields'] as $label => $value)
                                <div>
                                    <dt class="text-ink-faint">{{ __($label) }}</dt>
                                    <dd class="break-words text-ink">{{ $value }}</dd>
                                </div>
                            @endforeach
                        </dl>
                        @if ($account['history'] !== [])
                            <p class="mt-4 text-sm font-medium text-ink">{{ __('Payment history') }}</p>
                            <ol class="mt-2 flex flex-wrap gap-1.5" aria-label="{{ __('Payment history') }}">
                                @foreach ($account['history'] as $month)
                                    <li @class([
                                        'rounded-md px-2 py-1 text-center font-mono text-[0.65rem]',
                                        'bg-warn-soft text-warn' => $month['late'],
                                        'bg-pass-soft text-pass' => ! $month['late'],
                                    ]) title="{{ $month['month'] }}: {{ $month['status'] }}">
                                        <span class="block">{{ $month['month'] }}</span>
                                        <span class="block font-semibold">{{ $month['status'] }}</span>
                                    </li>
                                @endforeach
                            </ol>
                            <p class="mt-2 text-xs text-ink-faint">{{ __('Highlighted months were reported as late or overdue.') }}</p>
                        @endif
                    </div>
                </details>
            @empty
                <p class="mt-3 text-sm text-ink-muted">{{ __('The bureau reported no credit accounts.') }}</p>
            @endforelse
        </x-ui.card>

        <x-ui.card class="mt-4" data-report-section="enquiries">
            <h2 class="font-display text-lg font-semibold text-ink">{{ __('Recent credit enquiries') }}</h2>
            @if ($enquirySummary !== [])
                <dl class="mt-4 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($enquirySummary as $label => $value)
                        <div>
                            <dt class="text-ink-faint">{{ __($label) }}</dt>
                            <dd class="text-ink">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif
            @forelse ($enquiries as $enquiry)
                <dl class="mt-3 grid gap-x-6 gap-y-2 rounded-xl border border-line p-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($enquiry as $label => $value)
                        <div>
                            <dt class="text-ink-faint">{{ __($label) }}</dt>
                            <dd class="text-ink">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            @empty
                <p class="mt-3 text-sm text-ink-muted">{{ __('The bureau reported no recent enquiries.') }}</p>
            @endforelse
        </x-ui.card>
    @endif
</x-layouts.portal>
