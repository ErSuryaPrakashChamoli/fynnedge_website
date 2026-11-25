<x-filament-panels::page>
    @php
        $types = $this->types();
        $selected = $this->selected();
    @endphp

    <section>
        <h2 class="text-base font-semibold text-gray-950 dark:text-white">1. What do you want to publish?</h2>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Pick the closest match — each type opens its own editor and appears in a different part of the website.</p>

        <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" role="radiogroup" aria-label="Page type">
            @foreach ($types as $key => $type)
                <button type="button" wire:click="choose('{{ $key }}')" role="radio" aria-checked="{{ $this->type === $key ? 'true' : 'false' }}"
                    @class([
                        'fe-page-type flex h-full flex-col rounded-2xl border p-5 text-left shadow-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500',
                        'border-primary-500 bg-primary-50 ring-2 ring-primary-500/30 dark:bg-primary-500/10' => $this->type === $key,
                        'border-gray-200 bg-white hover:border-primary-400 hover:bg-primary-50 hover:shadow-md dark:border-white/10 dark:bg-gray-900 dark:hover:border-primary-400 dark:hover:bg-primary-500/10' => $this->type !== $key,
                    ])>
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-100 text-primary-700 dark:bg-primary-500/20 dark:text-primary-300">
                        <x-filament::icon :icon="$type['icon']" class="h-5 w-5" />
                    </span>
                    <span class="mt-3 text-sm font-semibold text-gray-950 dark:text-white">{{ $type['title'] }}</span>
                    <span class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $type['tagline'] }}</span>
                    <span class="mt-2 text-xs text-gray-500">e.g. {{ $type['examples'] }}</span>
                    <span class="mt-auto pt-3 [overflow-wrap:anywhere] font-mono text-[11px] text-gray-500">{{ $type['address'] }}</span>
                </button>
            @endforeach
        </div>
    </section>

    @if ($selected)
        <section class="grid gap-6 lg:grid-cols-5">
            <div class="space-y-4 lg:col-span-2">
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">2. About a {{ strtolower($selected['title']) }}</h2>
                <dl class="space-y-3 rounded-2xl border border-gray-200 bg-white p-5 text-sm dark:border-white/10 dark:bg-gray-900">
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Web address</dt><dd class="mt-0.5 font-mono [overflow-wrap:anywhere] text-xs text-gray-800 dark:text-gray-200">{{ $selected['address'] }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">On the website</dt><dd class="mt-0.5 text-gray-700 dark:text-gray-300">{{ $selected['appears'] }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Menus &amp; footer</dt><dd class="mt-0.5 text-gray-700 dark:text-gray-300">{{ $selected['navigation'] }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Search engines</dt><dd class="mt-0.5 text-gray-700 dark:text-gray-300">{{ $selected['indexing'] }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Publishing</dt><dd class="mt-0.5 text-gray-700 dark:text-gray-300">New pages start as drafts — nothing is visible until you publish.</dd></div>
                </dl>

                @if ($selected['requirements'] !== [])
                    <div class="rounded-2xl border border-gray-200 bg-white p-5 text-sm dark:border-white/10 dark:bg-gray-900">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Needs first</p>
                        <ul class="mt-2 space-y-2">
                            @foreach ($selected['requirements'] as $requirement)
                                <li class="flex items-start gap-2">
                                    @if ($requirement['met'])
                                        <x-filament::icon icon="heroicon-s-check-circle" class="mt-0.5 h-4 w-4 shrink-0 text-success-600" />
                                    @else
                                        <x-filament::icon icon="heroicon-s-x-circle" class="mt-0.5 h-4 w-4 shrink-0 {{ $requirement['key'] === 'author' ? 'text-warning-500' : 'text-danger-600' }}" />
                                    @endif
                                    <span>
                                        <span class="font-medium text-gray-900 dark:text-white">{{ $requirement['label'] }}</span>
                                        <span class="text-gray-500">— {{ $requirement['met'] ? $requirement['count'].' available' : ($requirement['key'] === 'author' ? 'none yet (recommended)' : 'missing') }}</span>
                                        <span class="block text-xs text-gray-500">{{ $requirement['why'] }}</span>
                                        @if (! $requirement['met'] && $requirement['url'])
                                            <a href="{{ $requirement['url'] }}" class="text-xs font-semibold text-primary-600 hover:underline">{{ $requirement['action'] }} →</a>
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            <div class="space-y-4 lg:col-span-3">
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">3. The steps</h2>
                @include('filament.admin.workflow-steps', ['workflow' => $selected['workflow'], 'layout' => 'vertical'])

                <div class="flex flex-col gap-3 rounded-2xl border border-gray-200 bg-gray-50 p-5 sm:flex-row sm:items-center sm:justify-between dark:border-white/10 dark:bg-white/5">
                    <div class="text-sm">
                        @if ($selected['blocked'])
                            <p class="font-semibold text-danger-700 dark:text-danger-400">Complete the highlighted step first.</p>
                            <p class="text-gray-600 dark:text-gray-400">This page type cannot be created until it exists.</p>
                        @elseif ($selected['createUrl'])
                            <p class="font-semibold text-gray-950 dark:text-white">Ready — open the editor.</p>
                            <p class="text-gray-600 dark:text-gray-400">It opens as a new draft; nothing is published yet.</p>
                        @else
                            <p class="font-semibold text-gray-950 dark:text-white">Your role cannot create this page type.</p>
                            <p class="text-gray-600 dark:text-gray-400">Ask an administrator, or edit existing pages if you have access.</p>
                        @endif
                    </div>
                    <div class="flex shrink-0 gap-2">
                        @if ($selected['listUrl'])
                            <x-filament::button tag="a" :href="$selected['listUrl']" color="gray">See existing</x-filament::button>
                        @endif
                        @if ($selected['createUrl'] && ! $selected['blocked'])
                            <x-filament::button tag="a" :href="$selected['createUrl']" icon="heroicon-m-plus">Create {{ strtolower($selected['title']) }}</x-filament::button>
                        @endif
                    </div>
                </div>
            </div>
        </section>
    @else
        <x-filament::section>
            <x-slot name="heading">General page or loan landing page?</x-slot>
            <div class="grid gap-4 text-sm md:grid-cols-2">
                <div>
                    <p class="font-semibold text-gray-950 dark:text-white">General page</p>
                    <p class="mt-1 text-gray-600 dark:text-gray-400">Information only — no enquiry form, no rates. Lives at fynnedge.com/{address}. Use it for policies, company or guide pages.</p>
                </div>
                <div>
                    <p class="font-semibold text-gray-950 dark:text-white">Loan landing page</p>
                    <p class="mt-1 text-gray-600 dark:text-gray-400">Sells one loan product: enquiry form, live rates and calculator built in, listed in the Loans menu. Lives at fynnedge.com/loans/{product}/{address}. Use it to win searches and leads.</p>
                </div>
            </div>
        </x-filament::section>
    @endif

    @if ($fixed = $this->fixedPages())
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Pages that already exist and are only edited</x-slot>
            <ul class="grid gap-3 sm:grid-cols-2">
                @foreach ($fixed as $page)
                    <li><a href="{{ $page['url'] }}" class="font-medium text-primary-600 hover:underline">{{ $page['label'] }}</a><span class="block text-xs text-gray-500">{{ $page['detail'] }}</span></li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif
</x-filament-panels::page>
