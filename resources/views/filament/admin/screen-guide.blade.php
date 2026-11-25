{{--
    "About this screen" — the full description at the bottom of every admin screen, in
    clearly separate blocks: what it does, when to use it, where it shows on the website,
    then what it depends on (live), related screens and the suggested next step.
    Collapsed by default; the one-line summary is always visible.
    Data: App\Support\Admin\AdminGuide.
--}}
@if ($guide)
    @php
        $blocks = [
            ['heading' => 'What it does', 'text' => $guide['purpose'], 'icon' => 'heroicon-o-light-bulb', 'tone' => 'bg-sky-50 text-sky-600 ring-sky-100 dark:bg-sky-500/10 dark:text-sky-400 dark:ring-sky-500/20'],
            ['heading' => 'When to use it', 'text' => $guide['when'], 'icon' => 'heroicon-o-clock', 'tone' => 'bg-amber-50 text-amber-600 ring-amber-100 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-500/20'],
            ['heading' => 'On the website', 'text' => $guide['website'] ?? 'Only in the admin. Visitors never see anything from this screen.', 'icon' => 'heroicon-o-globe-alt', 'tone' => 'bg-emerald-50 text-emerald-600 ring-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/20'],
        ];
    @endphp

    <details class="fe-screen-guide group mt-4 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <summary class="flex cursor-pointer list-none items-center gap-4 px-5 py-4 fe-hover sm:px-6">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-primary-500 to-primary-700 text-white shadow-sm shadow-primary-600/30">
                <x-filament::icon icon="heroicon-m-information-circle" class="h-5 w-5" />
            </span>
            <span class="min-w-0 flex-1">
                <span class="block text-[0.9375rem] font-bold text-gray-950 dark:text-white">About this screen</span>
                <span class="mt-0.5 block text-sm text-gray-500 dark:text-gray-400">{{ $guide['summary'] }}</span>
            </span>
            <span class="hidden shrink-0 items-center gap-1.5 rounded-full bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-600 sm:inline-flex dark:bg-white/5 dark:text-gray-300">
                <span class="group-open:hidden">Show details</span>
                <span class="hidden group-open:inline">Hide details</span>
                <x-filament::icon icon="heroicon-m-chevron-down" class="h-4 w-4 transition-transform duration-200 group-open:rotate-180" />
            </span>
        </summary>

        <div class="border-t border-gray-100 dark:border-white/5">
            <div class="grid divide-y divide-gray-100 md:grid-cols-3 md:divide-x md:divide-y-0 dark:divide-white/5">
                @foreach ($blocks as $block)
                    <section class="flex gap-3.5 p-5 sm:p-6">
                        <span @class(['flex h-9 w-9 shrink-0 items-center justify-center rounded-xl ring-1', $block['tone']])>
                            <x-filament::icon :icon="$block['icon']" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0">
                            <h3 class="text-sm font-bold text-gray-950 dark:text-white">{{ $block['heading'] }}</h3>
                            <p class="mt-1.5 text-sm leading-relaxed text-gray-600 dark:text-gray-300">{{ $block['text'] }}</p>
                        </div>
                    </section>
                @endforeach
            </div>

            @if ($requirements !== [] || $related !== [] || $next)
                <div class="grid gap-6 border-t border-gray-100 bg-gray-50/70 px-5 py-5 sm:px-6 lg:grid-cols-3 dark:border-white/5 dark:bg-white/[0.02]">
                    @if ($requirements !== [])
                        <div>
                            <p class="text-[0.6875rem] font-bold uppercase tracking-[0.1em] text-gray-500 dark:text-gray-400">Needs these first</p>
                            <ul class="mt-2.5 flex flex-wrap gap-2">
                                @foreach ($requirements as $requirement)
                                    @php
                                        $chipClasses = \Illuminate\Support\Arr::toCssClasses([
                                            'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold ring-1 transition',
                                            'bg-success-50 text-success-700 ring-success-200 dark:bg-success-500/10 dark:text-success-300 dark:ring-success-500/20' => $requirement['met'],
                                            'bg-danger-50 text-danger-700 ring-danger-200 dark:bg-danger-500/10 dark:text-danger-300 dark:ring-danger-500/20' => ! $requirement['met'],
                                        ]);
                                    @endphp
                                    <li>
                                        @if ($requirement['url'])
                                            <a href="{{ $requirement['url'] }}" class="{{ $chipClasses }} hover:brightness-95">
                                                <x-filament::icon :icon="$requirement['met'] ? 'heroicon-s-check-circle' : 'heroicon-s-x-circle'" class="h-4 w-4" />
                                                {{ $requirement['label'] }}
                                                <span class="font-medium opacity-70">· {{ $requirement['met'] ? $requirement['count'].' ready' : 'missing' }}</span>
                                            </a>
                                        @else
                                            <span class="{{ $chipClasses }}">
                                                <x-filament::icon :icon="$requirement['met'] ? 'heroicon-s-check-circle' : 'heroicon-s-x-circle'" class="h-4 w-4" />
                                                {{ $requirement['label'] }}
                                                <span class="font-medium opacity-70">· {{ $requirement['met'] ? $requirement['count'].' ready' : 'missing' }}</span>
                                            </span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if ($related !== [])
                        <div>
                            <p class="text-[0.6875rem] font-bold uppercase tracking-[0.1em] text-gray-500 dark:text-gray-400">Related screens</p>
                            <div class="mt-2.5 flex flex-wrap gap-2">
                                @foreach ($related as $link)
                                    <a href="{{ $link['url'] }}" class="inline-flex items-center gap-1 rounded-full bg-white px-3 py-1 text-xs font-semibold text-gray-700 shadow-sm ring-1 ring-gray-200 transition hover:bg-primary-100 hover:text-primary-800 hover:ring-primary-400 dark:bg-white/5 dark:hover:bg-primary-500/20 dark:hover:text-white dark:text-gray-200 dark:ring-white/10">
                                        {{ $link['label'] }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($next)
                        <div>
                            <p class="text-[0.6875rem] font-bold uppercase tracking-[0.1em] text-gray-500 dark:text-gray-400">Suggested next step</p>
                            <a href="{{ $next['url'] }}" class="mt-2.5 inline-flex items-center gap-2 rounded-xl bg-primary-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm shadow-primary-600/30 transition hover:bg-primary-500">
                                {{ $next['label'] }}
                                <x-filament::icon icon="heroicon-m-arrow-right" class="h-4 w-4" />
                            </a>
                        </div>
                    @endif
                </div>
            @endif

            @if ($helpUrl)
                <p class="flex items-center gap-2 border-t border-gray-100 px-5 py-3 text-xs text-gray-500 sm:px-6 dark:border-white/5 dark:text-gray-400">
                    <x-filament::icon icon="heroicon-m-lifebuoy" class="h-4 w-4 text-orange-500" />
                    New to the admin? <a href="{{ $helpUrl }}" class="font-semibold text-primary-600 hover:underline dark:text-primary-400">Help &amp; Guides</a> shows the order to do things in.
                </p>
            @endif
        </div>
    </details>
@endif
