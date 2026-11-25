<x-filament-widgets::widget>
    @php
        $items = $this->items();
        $typeTones = [
            'General page' => 'bg-indigo-500 ring-indigo-100 dark:ring-indigo-500/20',
            'Landing page' => 'bg-emerald-500 ring-emerald-100 dark:ring-emerald-500/20',
            'Article' => 'bg-amber-500 ring-amber-100 dark:ring-amber-500/20',
        ];
    @endphp

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-4 dark:border-white/5">
            <div>
                <h2 class="text-[0.9375rem] font-bold text-gray-950 dark:text-white">Recently updated content</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400">What the team has been working on, newest first</p>
            </div>
        </div>

        @if ($items === [])
            <p class="px-5 py-8 text-center text-sm text-gray-500">Nothing yet.</p>
        @else
            <ol class="grid gap-x-8 px-5 py-4 lg:grid-cols-2">
                @foreach ($items as $item)
                    <li class="relative flex gap-4 pb-4 last:pb-0">
                        <span class="relative flex w-3 shrink-0 justify-center">
                            <span @class(['relative z-10 mt-1.5 h-3 w-3 rounded-full ring-4', $typeTones[$item['type']] ?? 'bg-gray-400 ring-gray-100'])></span>
                            @unless ($loop->last)
                                <span class="absolute top-5 bottom-[-0.5rem] w-px bg-gray-200 dark:bg-white/10" aria-hidden="true"></span>
                            @endunless
                        </span>
                        <div class="flex min-w-0 flex-1 items-start justify-between gap-3">
                            <div class="min-w-0">
                                @if ($item['url'])
                                    <a href="{{ $item['url'] }}" class="block truncate text-sm font-semibold text-gray-950 hover:text-primary-600 dark:text-white">{{ $item['title'] }}</a>
                                @else
                                    <span class="block truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $item['title'] }}</span>
                                @endif
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $item['type'] }} · updated {{ $item['updated'] }}</span>
                            </div>
                            <x-filament::badge :color="$item['live'] ? 'success' : 'warning'" size="sm" class="shrink-0">{{ $item['live'] ? 'Live' : $item['status'] }}</x-filament::badge>
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>
</x-filament-widgets::widget>
