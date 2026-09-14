<x-layouts.app :title="$content['lenders_label']" :description="$content['partners_description']" page-type="CollectionPage">
    @php
        /*
            Every active lender (Admin → Catalog → Lenders), one card each. The
            logo sits on a white panel in both themes, because uploaded logos are
            drawn for a light background. Grouped by lender type only when more
            than one type is present — a single heading over the whole grid adds
            nothing.
        */
        $groupHeadings = [
            \App\Enums\LenderType::Bank->value => 'Banks',
            \App\Enums\LenderType::Nbfc->value => 'NBFCs & HFCs',
        ];

        $groups = collect(\App\Enums\LenderType::cases())
            ->mapWithKeys(fn (\App\Enums\LenderType $type): array => [$groupHeadings[$type->value] => $lenders->filter(fn ($lender): bool => $lender->type === $type)])
            ->put('Other lenders', $lenders->filter(fn ($lender): bool => $lender->type === null))
            ->filter(fn ($group): bool => $group->isNotEmpty());
    @endphp

    <section class="border-b border-line bg-surface-2" data-ai-context="Partner Banks and NBFCs">
        <div class="mx-auto max-w-7xl px-6 py-12 lg:px-8 lg:py-14">
            <x-ui.breadcrumbs :trail="[$content['lenders_label'] => null]" />

            <h1 data-reveal="up" class="mt-5 text-balance font-display text-3xl font-semibold tracking-tight text-ink sm:text-4xl">
                {{ $content['lenders_label'] }}
            </h1>
            <p data-reveal="up" class="mt-3 max-w-2xl text-lg text-ink-muted">
                {{ $content['partners_description'] }}
            </p>

            <div data-reveal="up delay-1" class="mt-6 flex flex-wrap items-center gap-3">
                <x-ui.button tag="a" href="{{ route('quick-enquiry.show') }}">{{ $content['home_button_label'] }}</x-ui.button>
                <x-ui.button tag="a" variant="secondary" href="{{ route('eligibility.index') }}">Check Your Eligibility</x-ui.button>
            </div>
        </div>
    </section>

    <section class="bg-surface">
        <div class="mx-auto max-w-7xl px-6 py-12 lg:px-8 lg:py-14">
            @if ($lenders->isEmpty())
                <x-ui.alert tone="accent">
                    Our partner list is being updated. Check back shortly.
                </x-ui.alert>
            @else
                @foreach ($groups as $heading => $group)
                    <div @class(['mt-12' => ! $loop->first])>
                        @if ($groups->count() > 1)
                            <h2 data-reveal="up" class="mb-5 flex items-baseline gap-2 font-display text-xl font-semibold text-ink">
                                {{ $heading }}
                                <span class="font-mono text-xs font-medium text-ink-faint">{{ $group->count() }}</span>
                            </h2>
                        @endif

                        <ul class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                            @foreach ($group as $lender)
                                <li data-reveal="zoom stagger" class="flex flex-col overflow-hidden rounded-2xl border border-line bg-surface shadow-sm transition-shadow hover:shadow-md">
                                    <div class="flex h-28 items-center justify-center bg-white p-5 sm:h-32">
                                        @if ($lender->logoUrl())
                                            <img src="{{ $lender->logoUrl() }}" alt="{{ $lender->name }} logo" loading="lazy" class="max-h-full max-w-full object-contain">
                                        @else
                                            <x-ui.lender-logo :lender="$lender" size="lg" />
                                        @endif
                                    </div>
                                    <div class="flex-1 border-t border-line px-4 py-3">
                                        <p class="text-sm font-semibold leading-snug text-ink">{{ $lender->name }}</p>
                                        @if ($lender->type)
                                            <p class="mt-0.5 font-mono text-[0.65rem] uppercase tracking-wider text-ink-faint">{{ $lender->type->getLabel() }}</p>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            @endif
        </div>
    </section>
</x-layouts.app>
