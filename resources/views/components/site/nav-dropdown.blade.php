@props(['label', 'activeWhen' => null])

@php $isActive = $activeWhen && request()->routeIs($activeWhen); @endphp

<div x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false" @mouseenter="open = true" @mouseleave="open = false" class="relative">
    <button
        type="button"
        @click="open = !open"
        :aria-expanded="open"
        @if ($isActive) aria-current="true" @endif
        @class([
            'flex cursor-pointer items-center gap-1 text-sm font-medium transition-colors',
            'text-accent font-semibold' => $isActive,
            'text-ink-muted hover:text-ink' => ! $isActive,
        ])
    >
        {{ $label }}
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="h-3.5 w-3.5 transition-transform" :class="{ 'rotate-180': open }"><path stroke-linecap="round" stroke-linejoin="round" d="m5 7.5 5 5 5-5" /></svg>
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        @click="open = false"
        class="absolute left-0 top-full z-10 w-max pt-3"
    >
        <div class="nav-dropdown-panel flex flex-col gap-1 rounded-xl border border-line bg-surface p-2 shadow-lg">
            {{ $slot }}
        </div>
    </div>
</div>
