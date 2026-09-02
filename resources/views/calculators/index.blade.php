<x-layouts.app title="Calculators" description="Loan EMI, loan eligibility, prepayment and investment calculators — fixed deposit, SIP, GST and more.">
    <section class="mx-auto max-w-7xl px-6 py-14 lg:px-8">
        <x-ui.breadcrumbs :trail="['Calculators' => null]" />

        <h1 data-reveal="up" class="mt-5 max-w-2xl text-balance font-display text-3xl font-semibold tracking-tight text-ink sm:text-4xl">
            All calculators
        </h1>
        <p data-reveal="up" class="mt-3 max-w-xl text-ink-muted">
            Loan EMI, eligibility and prepayment calculators, plus everyday investment calculators — all in one place.
        </p>

        <div class="mt-10 grid gap-10 lg:grid-cols-3">
            @foreach ($groups as $groupLabel => $items)
                <div data-reveal="up stagger">
                    <h2 class="font-display text-lg font-semibold text-ink">{{ $groupLabel }}</h2>
                    <div class="mt-4 flex flex-col gap-3">
                        @foreach ($items as $item)
                            @if (Route::has($item['route']))
                                <a href="{{ route($item['route'], $item['params']) }}" class="group">
                                    <x-ui.card class="card-lift transition-colors transition-shadow group-hover:bg-accent-soft group-hover:shadow-md group-hover:animate-card-swing">
                                        <span class="flex items-center justify-between gap-2 text-sm font-medium text-ink group-hover:text-accent">
                                            {{ $item['label'] }}
                                            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" class="h-3.5 w-3.5 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8h10m0 0-4-4m4 4-4 4" /></svg>
                                        </span>
                                    </x-ui.card>
                                </a>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </section>
</x-layouts.app>
