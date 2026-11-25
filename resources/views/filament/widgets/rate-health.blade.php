<x-filament-widgets::widget class="h-full">
    @php
        $health = $this->health();
        $circumference = 2 * M_PI * 42;
        $ringTone = $health['live'] === $health['offers'] ? '#10b981' : '#f59e0b';
        $tones = [
            'success' => ['dot' => 'bg-success-500', 'pill' => 'bg-success-50 text-success-700 ring-success-200 dark:bg-success-500/10 dark:text-success-300 dark:ring-success-500/20'],
            'warning' => ['dot' => 'bg-warning-500', 'pill' => 'bg-warning-50 text-warning-700 ring-warning-200 dark:bg-warning-500/10 dark:text-warning-300 dark:ring-warning-500/20'],
            'danger' => ['dot' => 'bg-danger-500', 'pill' => 'bg-danger-50 text-danger-700 ring-danger-200 dark:bg-danger-500/10 dark:text-danger-300 dark:ring-danger-500/20'],
            'gray' => ['dot' => 'bg-gray-300 dark:bg-gray-600', 'pill' => 'bg-gray-50 text-gray-600 ring-gray-200 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10'],
        ];
    @endphp

    <div class="flex h-full flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-4 dark:border-white/5">
            <div>
                <h2 class="text-[0.9375rem] font-bold text-gray-950 dark:text-white">Interest rates</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400">How complete and up to date the published rates are</p>
            </div>
        </div>

        <div class="flex flex-1 flex-col gap-5 p-5 md:flex-row md:items-center">
            <a href="{{ $health['liveUrl'] }}" class="group flex shrink-0 items-center gap-4 md:w-60 md:flex-col md:text-center">
                <span class="relative block h-28 w-28 shrink-0">
                    <svg viewBox="0 0 100 100" class="h-full w-full -rotate-90" aria-hidden="true">
                        <circle cx="50" cy="50" r="42" fill="none" stroke="currentColor" stroke-width="10" class="text-gray-100 dark:text-white/10" />
                        <circle cx="50" cy="50" r="42" fill="none" stroke="{{ $ringTone }}" stroke-width="10" stroke-linecap="round"
                            stroke-dasharray="{{ round($circumference, 2) }}"
                            stroke-dashoffset="{{ round($circumference * (1 - $health['percent'] / 100), 2) }}" />
                    </svg>
                    <span class="absolute inset-0 flex flex-col items-center justify-center">
                        <span class="text-2xl font-extrabold tracking-tight text-gray-950 tabular-nums dark:text-white">{{ $health['percent'] }}%</span>
                        <span class="text-[0.625rem] font-bold uppercase tracking-wider text-gray-400">live</span>
                    </span>
                </span>
                <span class="min-w-0">
                    <span class="block text-sm font-bold text-gray-950 group-hover:text-primary-700 dark:text-white">Offers with a live verified rate</span>
                    <span class="mt-0.5 block text-lg font-extrabold text-gray-950 tabular-nums dark:text-white">{{ $health['live'] }} / {{ $health['offers'] }}</span>
                    <span class="block text-xs text-gray-500 dark:text-gray-400">Active lender offers; the rest show "Check current rates"</span>
                </span>
            </a>

            <ul class="min-w-0 flex-1 divide-y divide-gray-100 rounded-xl ring-1 ring-gray-100 dark:divide-white/5 dark:ring-white/5">
                @foreach ($health['rows'] as $row)
                    <li>
                        <a href="{{ $row['url'] }}" class="group flex items-center gap-3 px-4 py-3 fe-hover">
                            <span @class(['h-2.5 w-2.5 shrink-0 rounded-full', $tones[$row['tone']]['dot']])></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-semibold text-gray-950 dark:text-white">{{ $row['label'] }}</span>
                                <span class="block truncate text-xs text-gray-500 dark:text-gray-400" title="{{ $row['description'] }}">{{ $row['description'] }}</span>
                            </span>
                            <span @class(['shrink-0 rounded-full px-2.5 py-0.5 text-sm font-bold tabular-nums ring-1', $tones[$row['tone']]['pill']])>{{ $row['value'] }}</span>
                            <x-filament::icon icon="heroicon-m-chevron-right" class="h-4 w-4 shrink-0 text-gray-300 transition group-hover:translate-x-0.5 group-hover:text-primary-500 dark:text-gray-600" />
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</x-filament-widgets::widget>
