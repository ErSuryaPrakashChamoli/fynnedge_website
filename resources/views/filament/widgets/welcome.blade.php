<x-filament-widgets::widget>
    @php
        $data = $this->data();
    @endphp
    <div class="fe-welcome relative isolate overflow-hidden rounded-3xl bg-[#13284a] text-white shadow-lg shadow-primary-950/20">
        {{-- Decorative light: soft colour glows and a faint grid. --}}
        <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
            <div class="absolute -top-24 -right-20 h-80 w-80 rounded-full bg-sky-400/30 blur-3xl"></div>
            <div class="absolute -bottom-32 left-1/3 h-80 w-[28rem] rounded-full bg-emerald-400/20 blur-3xl"></div>
            <div class="absolute -left-16 top-0 h-64 w-64 rounded-full bg-indigo-500/30 blur-3xl"></div>
            <div class="absolute inset-0 opacity-[0.07] [background-image:linear-gradient(to_right,white_1px,transparent_1px),linear-gradient(to_bottom,white_1px,transparent_1px)] [background-size:28px_28px] [mask-image:linear-gradient(to_bottom,black,transparent)]"></div>
        </div>

        <div class="flex flex-col gap-6 p-6 sm:p-8 lg:flex-row lg:items-center lg:justify-between">
            <div class="min-w-0">
                <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-[0.6875rem] font-bold uppercase tracking-[0.14em] text-white/80 ring-1 ring-white/15">
                    <x-filament::icon icon="heroicon-m-calendar-days" class="h-3.5 w-3.5" />
                    {{ $data['date'] }}
                </span>
                {{-- The dashboard has no page title, so this greeting is the page's main heading. --}}
                <h1 class="mt-3 text-3xl font-extrabold tracking-tight sm:text-[2.125rem]">
                    {{ $data['greeting'] }}{{ $data['name'] !== '' ? ', '.$data['name'] : '' }}
                </h1>
                <p class="mt-2 max-w-2xl text-[0.9375rem] leading-relaxed text-white/75">
                    @if ($data['leadsToday'] !== null)
                        <span class="font-semibold text-white">{{ $data['leadsToday'] === 0 ? 'No new leads yet today.' : $data['leadsToday'].' new '.\Illuminate\Support\Str::plural('lead', $data['leadsToday']).' today.' }}</span>
                    @endif
                    Pick a module on the left; its screens open as tabs along the top.
                </p>
            </div>

            <div class="flex flex-wrap gap-2 lg:max-w-xl lg:justify-end">
                @foreach ($data['actions'] as $action)
                    <a href="{{ $action['url'] }}" @class([
                        'inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition',
                        'bg-white text-primary-800 shadow-lg shadow-black/10 hover:-translate-y-0.5 hover:bg-sky-100 hover:shadow-xl hover:ring-2 hover:ring-sky-300' => $action['primary'],
                        'bg-white/10 text-white ring-1 ring-white/20 backdrop-blur hover:-translate-y-0.5 hover:bg-white/25 hover:ring-white/60' => ! $action['primary'],
                    ])>
                        <x-filament::icon :icon="$action['icon']" class="h-4 w-4" />
                        {{ $action['label'] }}
                    </a>
                @endforeach
            </div>
        </div>

        @if ($data['modules'] !== [])
            {{-- A dock of module shortcuts, each in its module's colour (theme.css: .fe-jump). --}}
            <nav class="fe-jump" aria-label="Jump to a module">
                <span class="fe-jump-label">
                    <x-filament::icon icon="heroicon-m-bolt" class="h-3.5 w-3.5" />
                    Jump to
                </span>
                <div class="fe-jump-scroller">
                    @foreach ($data['modules'] as $module)
                        <a href="{{ $module['url'] }}" title="{{ $module['summary'] }}" data-module="{{ $module['module'] }}" class="fe-jump-chip">
                            <span class="fe-jump-chip-icon">
                                <x-filament::icon :icon="$module['icon']" class="h-3.5 w-3.5" />
                            </span>
                            {{ $module['label'] }}
                        </a>
                    @endforeach
                </div>
            </nav>
        @endif
    </div>
</x-filament-widgets::widget>
