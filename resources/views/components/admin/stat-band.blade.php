{{--
    Admin key figures on one continuous band with hairline dividers, instead of a grid
    of separate cards. Each item: label, value, optional description, tone (dot colour),
    url (makes the cell a link), badge (shows the label as a Filament badge) and hint
    (hover text explaining the figure).

    <x-admin.stat-band :items="[['label' => 'Failed', 'value' => 3, 'description' => '…', 'tone' => 'danger']]" />
--}}
@props([
    'items' => [],
    'columns' => 4,
])

@php
    $columnClasses = match ((int) $columns) {
        2 => 'sm:grid-cols-2',
        3 => 'sm:grid-cols-2 lg:grid-cols-3',
        5 => 'sm:grid-cols-3 xl:grid-cols-5',
        6 => 'sm:grid-cols-3 xl:grid-cols-6',
        default => 'sm:grid-cols-2 xl:grid-cols-4',
    };

    $dots = [
        'primary' => 'bg-primary-500',
        'success' => 'bg-success-500',
        'warning' => 'bg-warning-500',
        'danger' => 'bg-danger-500',
        'info' => 'bg-info-500',
        'gray' => 'bg-gray-400',
    ];
@endphp

<div {{ $attributes->class(['fe-stat-band overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10']) }}>
    <div class="-me-px -mb-px grid grid-cols-1 {{ $columnClasses }}">
        @foreach ($items as $item)
            @php
                $tone = $item['tone'] ?? 'primary';
                $tag = filled($item['url'] ?? null) ? 'a' : 'div';
            @endphp
            <{{ $tag }}
                @if ($tag === 'a') href="{{ $item['url'] }}" @endif
                @if (filled($item['hint'] ?? null)) title="{{ $item['hint'] }}" @endif
                @class([
                    'fe-stat group relative flex min-w-0 flex-col gap-1.5 border-e border-b border-gray-100 px-5 py-4 dark:border-white/5',
                    'fe-hover' => $tag === 'a',
                ])
            >
                <span class="flex items-center gap-2">
                    @if ($item['badge'] ?? false)
                        <x-filament::badge :color="$tone" size="sm">{{ $item['label'] }}</x-filament::badge>
                    @else
                        <span @class(['h-2 w-2 shrink-0 rounded-full', $dots[$tone] ?? $dots['primary']])></span>
                        <span class="truncate text-[0.6875rem] font-bold uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">{{ $item['label'] }}</span>
                    @endif
                    @if ($tag === 'a')
                        <x-filament::icon icon="heroicon-m-arrow-up-right" class="ms-auto h-4 w-4 shrink-0 text-gray-300 transition group-hover:text-primary-500 dark:text-gray-600" />
                    @endif
                </span>
                <span class="text-[1.625rem] leading-8 font-extrabold tracking-tight text-gray-950 tabular-nums dark:text-white">{{ $item['value'] }}</span>
                @if (filled($item['description'] ?? null))
                    <span class="text-xs leading-5 text-gray-500 dark:text-gray-400">{{ $item['description'] }}</span>
                @endif
            </{{ $tag }}>
        @endforeach
    </div>
</div>
