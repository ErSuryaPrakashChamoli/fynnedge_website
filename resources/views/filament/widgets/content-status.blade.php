<x-filament-widgets::widget class="h-full">
    @php
        $content = $this->content();
    @endphp

    <div class="flex h-full flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-4 dark:border-white/5">
            <div>
                <h2 class="text-[0.9375rem] font-bold text-gray-950 dark:text-white">Website content</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400">What is live on the website and what is still a draft</p>
            </div>
            <span class="hidden items-center gap-3 text-[0.6875rem] font-semibold text-gray-500 sm:flex dark:text-gray-400">
                <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>Live</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-amber-400"></span>Draft</span>
            </span>
        </div>

        <ul class="space-y-1 p-3">
            @foreach ($content['pages'] as $row)
                @php
                    $total = max(1, $row['live'] + $row['draft']);
                @endphp
                <li>
                    <a href="{{ $row['url'] }}" class="group flex items-center gap-3.5 rounded-xl px-3 py-2.5 fe-hover">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400">
                            <x-filament::icon :icon="$row['icon']" class="h-5 w-5" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-baseline justify-between gap-3">
                                <span class="truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $row['label'] }}</span>
                                <span class="shrink-0 text-xs text-gray-500 tabular-nums dark:text-gray-400">
                                    <span class="font-bold text-emerald-600 dark:text-emerald-400">{{ $row['live'] }} live</span>
                                    · {{ $row['draft'] }} draft
                                </span>
                            </span>
                            <span class="mt-2 flex h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                                <span class="h-full bg-gradient-to-r from-emerald-400 to-emerald-500" style="width: {{ round($row['live'] / $total * 100, 1) }}%"></span>
                                <span class="h-full bg-amber-400" style="width: {{ round($row['draft'] / $total * 100, 1) }}%"></span>
                            </span>
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>

        @if ($content['newsletter'] !== [])
            <div class="mt-auto grid border-t border-gray-100 sm:grid-cols-2 sm:divide-x dark:divide-white/5 dark:border-white/5">
                @foreach ($content['newsletter'] as $item)
                    <a href="{{ $item['url'] }}" class="group flex items-center gap-3 px-5 py-4 fe-hover">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-fuchsia-50 text-fuchsia-600 dark:bg-fuchsia-500/10 dark:text-fuchsia-400">
                            <x-filament::icon :icon="$item['icon']" class="h-5 w-5" />
                        </span>
                        <span class="min-w-0">
                            <span class="block text-xs font-semibold text-gray-500 dark:text-gray-400">{{ $item['label'] }}</span>
                            <span class="block text-base font-extrabold text-gray-950 tabular-nums dark:text-white">{{ $item['value'] }}</span>
                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $item['note'] }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
