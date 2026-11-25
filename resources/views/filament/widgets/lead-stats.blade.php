<x-filament-widgets::widget>
    @php
        $m = $this->metrics();
        $spark = \App\Filament\Widgets\LeadStatsWidget::sparkline($m['daily']);
        $lmsTone = ! $m['lmsConnected'] || $m['abandoned'] > 0 ? 'warning' : 'success';
    @endphp

    <div class="fe-pulse overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-3 dark:border-white/5">
            <p class="flex items-center gap-2 text-sm font-bold text-gray-950 dark:text-white">
                <span class="relative flex h-2.5 w-2.5">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-sky-400 opacity-60 motion-reduce:hidden"></span>
                    <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-sky-500"></span>
                </span>
                Lead pulse
            </p>
            <span class="hidden text-xs text-gray-500 sm:inline dark:text-gray-400">Enquiries, score checks and applications · India time</span>
        </div>

        <div class="-me-px -mb-px grid sm:grid-cols-2 xl:grid-cols-4">
            {{-- Today --}}
            <a href="{{ $m['inboxUrl'] }}" class="group flex items-start gap-4 border-e border-b border-gray-100 p-5 fe-hover [--fe-accent:#0284c7] dark:border-white/5">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-sky-400 to-sky-600 text-white shadow-md shadow-sky-500/30">
                    <x-filament::icon icon="heroicon-m-inbox-stack" class="h-5 w-5" />
                </span>
                <span class="min-w-0">
                    <span class="block text-[0.6875rem] font-bold uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">New leads today</span>
                    <span class="mt-1 block text-3xl font-extrabold tracking-tight text-gray-950 tabular-nums dark:text-white">{{ number_format($m['today']) }}</span>
                    <span class="mt-0.5 flex items-center gap-1 text-xs font-medium text-sky-700 dark:text-sky-400">Open Lead Inbox <x-filament::icon icon="heroicon-m-arrow-right" class="h-3.5 w-3.5 transition group-hover:translate-x-0.5" /></span>
                </span>
            </a>

            {{-- Last 7 days with sparkline --}}
            <a href="{{ $m['analyticsUrl'] }}" class="group flex items-start gap-4 border-e border-b border-gray-100 p-5 fe-hover [--fe-accent:#059669] dark:border-white/5">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-400 to-emerald-600 text-white shadow-md shadow-emerald-500/30">
                    <x-filament::icon icon="heroicon-m-arrow-trending-up" class="h-5 w-5" />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block text-[0.6875rem] font-bold uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">Last 7 days</span>
                    <span class="mt-1 flex items-end justify-between gap-3">
                        <span class="text-3xl font-extrabold tracking-tight text-gray-950 tabular-nums dark:text-white">{{ number_format($m['week']) }}</span>
                        <svg viewBox="0 0 120 36" class="h-9 w-28 shrink-0 overflow-visible" role="img" aria-label="Leads per day, last 7 days: {{ implode(', ', $m['daily']) }}">
                            <defs>
                                <linearGradient id="fe-spark-fill" x1="0" x2="0" y1="0" y2="1">
                                    <stop offset="0%" stop-color="#10b981" stop-opacity="0.35" />
                                    <stop offset="100%" stop-color="#10b981" stop-opacity="0" />
                                </linearGradient>
                            </defs>
                            <path d="{{ $spark['area'] }}" fill="url(#fe-spark-fill)" />
                            <path d="{{ $spark['line'] }}" fill="none" stroke="#10b981" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </span>
                    <span class="mt-0.5 block text-xs font-medium text-emerald-700 dark:text-emerald-400">Daily trend · see Lead Analytics</span>
                </span>
            </a>

            {{-- Mobile verification: Lead Analytics opens on the same 30 days, with the OTP breakdown --}}
            <a href="{{ $m['analyticsUrl'] }}" class="group flex items-start gap-4 border-e border-b border-gray-100 p-5 fe-hover [--fe-accent:#7c3aed] dark:border-white/5">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-400 to-violet-600 text-white shadow-md shadow-violet-500/30">
                    <x-filament::icon icon="heroicon-m-check-badge" class="h-5 w-5" />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block text-[0.6875rem] font-bold uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">Mobile verified (30 days)</span>
                    <span class="mt-1 block text-3xl font-extrabold tracking-tight text-gray-950 tabular-nums dark:text-white">{{ $m['verifiedPercent'] === null ? '—' : $m['verifiedPercent'].'%' }}</span>
                    <span class="mt-2 block h-1.5 overflow-hidden rounded-full bg-violet-100 dark:bg-violet-500/15">
                        <span class="block h-full rounded-full bg-gradient-to-r from-violet-400 to-violet-600" style="width: {{ $m['verifiedPercent'] ?? 0 }}%"></span>
                    </span>
                    <span class="mt-1.5 flex items-center gap-1 text-xs font-medium text-violet-700 dark:text-violet-400">{{ $m['verified'] }} of {{ $m['total'] }} leads verified by OTP <x-filament::icon icon="heroicon-m-arrow-right" class="h-3.5 w-3.5 shrink-0 transition group-hover:translate-x-0.5" /></span>
                </span>
            </a>

            {{-- FynnOn LMS --}}
            <a href="{{ $m['syncUrl'] }}" class="group flex items-start gap-4 border-e border-b border-gray-100 p-5 fe-hover [--fe-accent:#d97706] dark:border-white/5">
                <span @class([
                    'flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl text-white shadow-md',
                    'bg-gradient-to-br from-amber-400 to-amber-600 shadow-amber-500/30' => $lmsTone === 'warning',
                    'bg-gradient-to-br from-teal-400 to-teal-600 shadow-teal-500/30' => $lmsTone === 'success',
                ])>
                    <x-filament::icon :icon="$m['abandoned'] > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-arrow-path-rounded-square'" class="h-5 w-5" />
                </span>
                <span class="min-w-0">
                    <span class="block text-[0.6875rem] font-bold uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">FynnOn LMS (30 days)</span>
                    <span class="mt-1 block text-3xl font-extrabold tracking-tight text-gray-950 dark:text-white">{{ $m['lmsConnected'] ? number_format($m['delivered']).' delivered' : 'Not connected' }}</span>
                    <span @class([
                        'mt-0.5 block text-xs font-medium',
                        'text-amber-700 dark:text-amber-400' => $lmsTone === 'warning',
                        'text-teal-700 dark:text-teal-400' => $lmsTone === 'success',
                    ])>
                        @if (! $m['lmsConnected'])
                            Leads are kept on the website until the LMS is connected
                        @elseif ($m['abandoned'] > 0)
                            {{ $m['abandoned'] }} failed — needs retry
                        @else
                            No failed deliveries
                        @endif
                    </span>
                </span>
            </a>
        </div>
    </div>
</x-filament-widgets::widget>
