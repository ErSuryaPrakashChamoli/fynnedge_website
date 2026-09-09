@props(['achievements', 'derived' => []])

{{--
    The full-width figures strip that sits directly under the hero banner's
    lender marquee.

    Two sources, never mixed:
      - Admin-published Achievement rows, when any exist. Rendered exactly as
        typed (prefix + value + suffix) with no count-up, because an arbitrary
        admin string has no integer target to animate toward — see
        .ai/rules/achievements-views.md.
      - Otherwise the figures the app can derive from real published records
        (active lenders, published loan products, calculators, guides). These
        are counted live, so they can never state something the database does
        not actually contain.

    Nothing here is ever seeded or hardcoded as a business claim. Items are
    `flex-1`, so whatever number of them exists spreads evenly across the full
    row rather than bunching at the left.
--}}
@php
    $items = $achievements->isNotEmpty()
        ? $achievements->map(fn ($achievement) => [
            'value' => $achievement->displayValue(),
            'label' => $achievement->label,
            'countTo' => null,
        ])->all()
        : collect($derived)
            ->filter(fn (array $stat): bool => ($stat['value'] ?? 0) > 0)
            ->map(fn (array $stat) => [
                'value' => $stat['value'].'+',
                'label' => $stat['label'],
                'countTo' => $stat['value'],
            ])->values()->all();

    // Runs on mount rather than on scroll: this strip sits above the fold by
    // design, so "wait until it scrolls into view" would only race its own
    // fade-in. The short delay lets the reveal land before the numbers move.
    $countUp = <<<'JS'
        {
            value: 0,
            target: %d,
            init() {
                if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                    this.value = this.target;
                    return;
                }
                setTimeout(() => {
                    const start = performance.now();
                    const duration = 2200;
                    const tick = (now) => {
                        const progress = Math.min((now - start) / duration, 1);
                        this.value = Math.round(this.target * progress);
                        if (progress < 1) requestAnimationFrame(tick);
                    };
                    requestAnimationFrame(tick);
                }, 300);
            },
        }
    JS;
@endphp

@if ($items !== [])
    <section class="border-b border-line bg-surface-2">
        <div class="mx-auto flex max-w-7xl flex-wrap items-stretch divide-line px-6 lg:divide-x lg:px-8">
            @foreach ($items as $item)
                <div class="flex min-w-[9rem] flex-1 flex-col justify-center px-2 py-4 text-center lg:px-6">
                    @if ($item['countTo'] !== null)
                        <p
                            x-data="{{ sprintf($countUp, $item['countTo']) }}"
                            class="font-display text-2xl font-semibold tracking-tight text-ink lg:text-3xl"
                        ><span x-text="value">0</span>+</p>
                    @else
                        <p class="font-display text-2xl font-semibold tracking-tight text-ink lg:text-3xl">{{ $item['value'] }}</p>
                    @endif
                    <p class="mt-1 font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">{{ $item['label'] }}</p>
                </div>
            @endforeach
        </div>
    </section>
@endif
