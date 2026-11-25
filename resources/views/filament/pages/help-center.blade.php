<x-filament-panels::page>
    <div x-data="{ q: '' }" class="space-y-8">
        <section class="fe-hero relative isolate overflow-hidden rounded-3xl bg-[#13284a] p-6 text-white shadow-lg shadow-primary-950/20 sm:p-8">
            <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
                <div class="absolute -top-24 -right-20 h-72 w-72 rounded-full bg-orange-400/25 blur-3xl"></div>
                <div class="absolute -bottom-32 left-1/4 h-72 w-96 rounded-full bg-sky-400/25 blur-3xl"></div>
            </div>
            <p class="text-[0.6875rem] font-bold uppercase tracking-[0.14em] text-white/70">Help &amp; Guides</p>
            <h2 class="mt-2 text-3xl font-extrabold tracking-tight">How this admin is organised</h2>
            <p class="mt-2 max-w-3xl text-[0.9375rem] leading-relaxed text-white/80">
                The left sidebar lists the main modules. Inside a module, its screens are the tabs across the top of the page.
                Every screen explains itself under its title and in the "About this screen" panel at the bottom.
                You can open {{ $this->screenCount() }} screens with your role.
            </p>
            <div class="mt-5 max-w-xl">
                <label for="fe-help-search" class="sr-only">Find a screen</label>
                <input id="fe-help-search" type="search" x-model="q" placeholder="Find a screen — e.g. “enquiries”, “redirect”, “author”…"
                    class="w-full rounded-xl border-0 bg-white px-4 py-3 text-sm text-gray-900 shadow-lg shadow-black/10 placeholder:text-gray-500 focus:ring-4 focus:ring-white/30" />
            </div>
        </section>

        @if ($createPageUrl = $this->createPageUrl())
            <section class="flex flex-col gap-3 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 sm:flex-row sm:items-center sm:justify-between dark:bg-gray-900 dark:ring-white/10" x-show="! q">
                <div>
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">Want to add a page to the website?</h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">General page, loan landing page, campaign page or blog article — the Create a Page guide explains each and opens the right editor.</p>
                </div>
                <x-filament::button tag="a" :href="$createPageUrl" icon="heroicon-m-plus" class="shrink-0">Create a Page</x-filament::button>
            </section>
        @endif

        <section>
            <div x-show="! q">
                <h3 class="text-lg font-bold text-gray-950 dark:text-white">Modules</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Each module is one item in the left sidebar. Its screens are listed on the right; click any of them to open it.</p>
            </div>
            <div class="mt-4 divide-y divide-gray-100 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:divide-white/5 dark:bg-gray-900 dark:ring-white/10">
                @foreach ($this->modules() as $module)
                    <div class="fe-help-module grid gap-5 p-5 sm:p-6 lg:grid-cols-[minmax(0,20rem)_minmax(0,1fr)] lg:gap-8"
                        data-module="{{ $module['slug'] }}"
                        x-show="! q || $el.dataset.search.includes(q.toLowerCase())"
                        data-search="{{ strtolower($module['label'].' '.$module['summary'].' '.collect($module['screens'])->map(fn ($s) => $s['label'].' '.$s['summary'])->implode(' ')) }}">
                        <div>
                            <a href="{{ $module['url'] }}" class="group flex items-center gap-3">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-white shadow-md" style="background: var(--fe-accent); box-shadow: 0 8px 18px -10px var(--fe-accent)">
                                    <x-filament::icon :icon="$module['icon']" class="h-5 w-5" />
                                </span>
                                <span class="text-base font-bold text-gray-950 group-hover:underline dark:text-white">{{ $module['label'] }}</span>
                            </a>
                            <p class="mt-3 text-sm leading-relaxed text-gray-600 dark:text-gray-300">{{ $module['summary'] }}</p>
                            <p class="mt-3 inline-flex items-start gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-200" style="background: var(--fe-accent-soft)">
                                <x-filament::icon icon="heroicon-m-clock" class="mt-px h-3.5 w-3.5 shrink-0" style="color: var(--fe-accent)" />
                                <span>Use it for: {{ $module['when'] }}</span>
                            </p>
                        </div>
                        <ul class="grid content-start gap-1 sm:grid-cols-2 2xl:grid-cols-3">
                            @foreach ($module['screens'] as $screen)
                                <li x-show="! q || $el.dataset.search.includes(q.toLowerCase())" data-search="{{ strtolower($module['label'].' '.$screen['label'].' '.$screen['summary']) }}">
                                    <a href="{{ $screen['url'] }}" class="group flex h-full gap-2.5 rounded-xl px-3 py-2.5 fe-hover">
                                        <x-filament::icon icon="heroicon-m-arrow-right-circle" class="mt-0.5 h-4 w-4 shrink-0 text-gray-300 transition group-hover:text-[var(--fe-accent)] dark:text-gray-600" />
                                        <span class="min-w-0">
                                            <span class="block text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $screen['label'] }}</span>
                                            @if ($screen['summary'])
                                                <span class="mt-0.5 line-clamp-2 block text-xs leading-5 text-gray-500 dark:text-gray-400">{{ $screen['summary'] }}</span>
                                            @endif
                                        </span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="space-y-6" x-show="! q">
            <div>
                <h3 class="text-lg font-bold text-gray-950 dark:text-white">Step-by-step guides</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Each guide lists the steps in order. Ticked steps are already done on this site; the highlighted one is next.</p>
            </div>
            @foreach ($this->workflows() as $key => $workflow)
                <div id="guide-{{ $key }}" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 sm:p-6 dark:bg-gray-900 dark:ring-white/10">
                    <h4 class="text-base font-bold text-gray-950 dark:text-white">{{ $workflow['title'] }}</h4>
                    <p class="mb-4 mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $workflow['description'] }}</p>
                    @include('filament.admin.workflow-steps', ['workflow' => $workflow])
                </div>
            @endforeach
        </section>
    </div>
</x-filament-panels::page>
