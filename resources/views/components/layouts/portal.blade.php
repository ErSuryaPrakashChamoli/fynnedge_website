{{--
    The FynnOn customer portal shell: the site's own layout (header, footer,
    theme tokens, both languages), kept out of search, with the portal's
    navigation beside the page. On phones the navigation becomes a
    horizontally scrolling strip above the content.
--}}
@props(['title', 'heading' => null, 'customer'])

@php
    $portalNavigation = [
        ['route' => 'portal.dashboard', 'label' => __('Dashboard'), 'active' => 'portal.dashboard', 'icon' => 'M3 10.5 10 4l7 6.5V17a1 1 0 0 1-1 1h-4v-5H8v5H4a1 1 0 0 1-1-1v-6.5Z'],
        ['route' => 'portal.profile', 'label' => __('My Profile'), 'active' => 'portal.profile*', 'icon' => 'M10 10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 8a7 7 0 0 1 14 0H3Z'],
        ['route' => 'portal.credit-score', 'label' => __('My Credit Score'), 'active' => 'portal.credit-score*', 'icon' => 'M3 14a7 7 0 1 1 14 0M10 14l3-4'],
        ['route' => 'portal.reports.index', 'label' => __('My Credit Reports'), 'active' => 'portal.reports.*', 'icon' => 'M5 2h7l4 4v12H5V2Zm7 0v4h4M8 10h5M8 13h5'],
        ['route' => 'portal.apply', 'label' => __('Apply for a Loan'), 'active' => 'portal.apply*', 'icon' => 'M10 4v12M4 10h12'],
        ['route' => 'portal.applications', 'label' => __('My Loan Applications'), 'active' => 'portal.applications', 'icon' => 'M4 4h12v12H4V4Zm3 4h6M7 11h6'],
        ['route' => 'portal.documents', 'label' => __('My Documents'), 'active' => 'portal.documents*', 'icon' => 'M6 2h5l5 5v11H6V2Zm5 0v5h5'],
        ['route' => 'portal.support', 'label' => __('Help & Support'), 'active' => 'portal.support', 'icon' => 'M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm0-5v.01M8 8a2 2 0 1 1 3 1.7c-.6.4-1 .8-1 1.3'],
        ['route' => 'portal.settings', 'label' => __('Account Settings'), 'active' => 'portal.settings', 'icon' => 'M10 13a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm6.5-3-1.6-.6-.4-1 .7-1.5-1.4-1.4-1.5.7-1-.4L10.6 3H9.4l-.6 1.6-1 .4-1.5-.7-1.4 1.4.7 1.5-.4 1L3.5 10v1.2l1.6.6.4 1-.7 1.5 1.4 1.4 1.5-.7 1 .4.6 1.6h1.2l.6-1.6 1-.4 1.5.7 1.4-1.4-.7-1.5.4-1 1.6-.6V10Z'],
    ];
@endphp

<x-layouts.app :title="$title.' — '.config('customer_portal.brand')" robots="noindex, nofollow">
    <section class="mx-auto max-w-7xl px-6 py-10 lg:px-8" data-portal>
        <div class="flex flex-col gap-1 border-b border-line pb-6 sm:flex-row sm:items-end sm:justify-between">
            <div class="min-w-0">
                <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-accent">{{ config('customer_portal.brand') }}</p>
                <h1 class="mt-1 text-balance font-display text-2xl font-semibold tracking-tight text-ink sm:text-3xl">{{ $heading ?? $title }}</h1>
            </div>
            <div class="flex items-center gap-3 text-sm text-ink-muted">
                <span class="truncate">{{ $customer->full_name ?: '+91 '.$customer->maskedMobile() }}</span>
                <form method="POST" action="{{ route('portal.logout') }}">
                    @csrf
                    <x-ui.button type="submit" variant="secondary" size="sm">{{ __('Sign out') }}</x-ui.button>
                </form>
            </div>
        </div>

        <div class="mt-6 grid gap-8 lg:grid-cols-[230px_1fr]">
            <nav aria-label="{{ __('My account') }}" class="-mx-6 overflow-x-auto px-6 lg:mx-0 lg:overflow-visible lg:px-0">
                <ul class="flex gap-1.5 lg:sticky lg:top-24 lg:flex-col">
                    @foreach ($portalNavigation as $item)
                        @php $isActive = request()->routeIs($item['active']); @endphp
                        <li class="shrink-0">
                            <a
                                href="{{ route($item['route']) }}"
                                @if ($isActive) aria-current="page" @endif
                                @class([
                                    'flex items-center gap-2.5 whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                                    'bg-accent-soft text-accent' => $isActive,
                                    'text-ink-muted hover:bg-surface-2 hover:text-ink' => ! $isActive,
                                ])
                            >
                                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0" aria-hidden="true"><path d="{{ $item['icon'] }}" /></svg>
                                {{ $item['label'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <div class="min-w-0">
                @if (session('status'))
                    <x-ui.alert tone="pass" class="mb-6">{{ session('status') }}</x-ui.alert>
                @endif

                {{ $slot }}
            </div>
        </div>
    </section>
</x-layouts.app>
