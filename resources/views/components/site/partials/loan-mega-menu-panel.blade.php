{{--
    The desktop Loans mega menu panel: every loan type and all of its landing
    pages (~450 links), rendered into every page. It holds no per-visitor state
    (no active link, no token, nothing signed-in), so LoanMegaMenu::panelHtml()
    caches the rendered HTML per language and site address.
--}}
<div class="nav-dropdown-panel grid grid-cols-[max-content_1fr] rounded-xl border border-line bg-surface shadow-lg">
    <div class="flex flex-col gap-1 border-r border-line p-3">
        @foreach ($loanCategories as $category)
            <a
                href="{{ route($category['overviewRoute'], $category['overviewParams']) }}"
                @mouseenter="active = '{{ $category['overviewParams']['loanProduct'] }}'"
                class="whitespace-nowrap rounded-lg px-3 py-2 text-left text-sm font-medium transition-colors"
                :class="active === '{{ $category['overviewParams']['loanProduct'] }}' ? 'bg-accent-soft text-accent' : 'text-ink-muted hover:bg-surface-2 hover:text-ink'"
            >
                {{ $category['label'] }}
            </a>
        @endforeach
    </div>

    <div class="p-5">
        @foreach ($loanCategories as $category)
            <div x-show="active === '{{ $category['overviewParams']['loanProduct'] }}'" x-cloak>
                <div class="flex items-center justify-between gap-4 border-b border-line pb-4">
                    <div>
                        <p class="font-display text-base font-semibold text-ink">{{ $category['label'] }}</p>
                        <a href="{{ route($category['overviewRoute'], $category['overviewParams']) }}" class="text-xs font-medium text-ink-faint hover:text-ink">{{ __('View overview →') }}</a>
                    </div>
                    <x-ui.button tag="a" :href="route($category['applyRoute'], $category['applyParams'])" size="sm">
                        {{ __('Check Eligibility') }}
                    </x-ui.button>
                </div>

                @if (! empty($category['groups']))
                    <div class="mt-4 grid grid-cols-[repeat(3,max-content)] gap-6 xl:grid-cols-[repeat(4,max-content)]">
                        @foreach ($category['groups'] as $groupLabel => $items)
                            @php $isLocationGroup = $groupLabel === \App\Enums\LandingPageGroup::ByLocation->getLabel(); @endphp
                            <div>
                                <p class="font-mono text-[0.62rem] font-semibold uppercase tracking-wider text-ink-faint">{{ $groupLabel }}</p>
                                <div @class(['mt-2', 'grid grid-cols-2 gap-x-2' => $isLocationGroup, 'flex flex-col' => ! $isLocationGroup])>
                                    {{--
                                        Plain links, not x-site.nav-link: every panel renders on every
                                        page, and with 24 cities per product the component's per-link
                                        cost was ~0.8s per render (288 links: 811ms vs 110ms plain). Its active state never matched
                                        these links anyway (the bound landingPage is a model, not a slug).
                                    --}}
                                    @foreach ($items as $item)
                                        <a href="{{ route($item['route'], $item['params']) }}" @if ($item['label'] !== $item['title']) aria-label="{{ $item['title'] }}" @endif @class(['block rounded-lg px-2 py-1 text-sm font-medium leading-snug text-ink-muted transition-colors hover:bg-surface-2 hover:text-ink', 'whitespace-nowrap' => $isLocationGroup, 'max-w-[14rem]' => ! $isLocationGroup])>{{ $item['label'] }}</a>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
