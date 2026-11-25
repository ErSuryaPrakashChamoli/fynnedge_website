<x-filament-widgets::widget class="h-full">
    @php
        $avatarTones = [
            'primary' => 'from-primary-400 to-primary-700',
            'info' => 'from-sky-400 to-sky-600',
            'warning' => 'from-amber-400 to-orange-500',
            'success' => 'from-emerald-400 to-emerald-600',
            'gray' => 'from-gray-400 to-gray-600',
        ];
        $dotTones = [
            'success' => 'bg-success-500',
            'warning' => 'bg-warning-500',
            'danger' => 'bg-danger-500',
            'gray' => 'bg-gray-300 dark:bg-gray-600',
        ];
        $leads = $this->leads();
    @endphp

    <div class="flex h-full flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-4 dark:border-white/5">
            <div>
                <h2 class="text-[0.9375rem] font-bold text-gray-950 dark:text-white">Latest leads</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400">The newest people to contact FynnEdge</p>
            </div>
            @if ($inbox = $this->inboxUrl())
                <a href="{{ $inbox }}" class="inline-flex items-center gap-1 rounded-full bg-primary-50 px-3 py-1.5 text-xs font-semibold text-primary-700 transition hover:bg-primary-100 dark:bg-primary-500/10 dark:text-primary-300">
                    Open Lead Inbox
                    <x-filament::icon icon="heroicon-m-arrow-right" class="h-3.5 w-3.5" />
                </a>
            @endif
        </div>

        <ul class="flex-1 divide-y divide-gray-100 dark:divide-white/5">
            @forelse ($leads as $lead)
                <li>
                    <a @if ($lead['url']) href="{{ $lead['url'] }}" @endif class="group flex items-center gap-3.5 px-5 py-3 fe-hover">
                        <span @class([
                            'flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br text-xs font-bold text-white shadow-sm',
                            $avatarTones[$lead['sourceColor']] ?? $avatarTones['gray'],
                        ])>{{ $lead['initials'] }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center gap-2">
                                <span class="truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $lead['name'] ?: 'Name not given' }}</span>
                                <x-filament::badge :color="$lead['sourceColor']" size="sm">{{ $lead['source'] }}</x-filament::badge>
                            </span>
                            <span class="mt-0.5 flex items-center gap-1.5 truncate text-xs text-gray-500 dark:text-gray-400">
                                @if ($lead['phone'])
                                    <span class="tabular-nums">{{ $lead['phone'] }}</span>
                                    <span aria-hidden="true">·</span>
                                @endif
                                <span>{{ $lead['product'] }}</span>
                            </span>
                        </span>
                        <span class="flex shrink-0 flex-col items-end gap-1">
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400" title="{{ $lead['at'] }}">{{ $lead['ago'] }}</span>
                            <span class="inline-flex items-center gap-1.5 text-[0.6875rem] font-medium text-gray-500 dark:text-gray-400" title="FynnOn LMS">
                                <span @class(['h-1.5 w-1.5 rounded-full', $dotTones[$lead['lmsColor']] ?? $dotTones['gray']])></span>
                                {{ $lead['lms'] }}
                            </span>
                        </span>
                    </a>
                </li>
            @empty
                <li class="flex flex-col items-center gap-2 px-5 py-12 text-center">
                    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-primary-50 text-primary-500 dark:bg-primary-500/10">
                        <x-filament::icon icon="heroicon-o-inbox" class="h-6 w-6" />
                    </span>
                    <span class="text-sm font-semibold text-gray-950 dark:text-white">No leads yet</span>
                    <span class="text-xs text-gray-500">New enquiries, score checks and applications will appear here.</span>
                </li>
            @endforelse
        </ul>
    </div>
</x-filament-widgets::widget>
