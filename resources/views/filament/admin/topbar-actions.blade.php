{{-- Shortcuts at the right of the top bar, before search (AdminPanelProvider, GLOBAL_SEARCH_BEFORE). --}}
<div class="fe-topbar-actions hidden items-center gap-2 md:flex">
    <span class="fe-topbar-date">
        <x-filament::icon icon="heroicon-m-calendar-days" class="h-4 w-4" />
        {{ now('Asia/Kolkata')->format('D, j M Y') }}
    </span>
    <a href="{{ route('home') }}" target="_blank" rel="noopener" class="fe-topbar-link">
        <x-filament::icon icon="heroicon-m-globe-alt" class="h-4 w-4" />
        View website
        <x-filament::icon icon="heroicon-m-arrow-top-right-on-square" class="h-3.5 w-3.5 opacity-60" />
    </a>
</div>
