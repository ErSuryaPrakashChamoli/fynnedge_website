<x-layouts.app :title="'Loan Products'" description="Compare personal loans, home loans, car loans, business loans, loans against property and credit cards — matched to lenders based on your profile.">
    <section class="mx-auto max-w-7xl px-6 py-14 lg:px-8">
        <x-ui.breadcrumbs :trail="['Loans' => null]" />

        <h1 class="mt-5 max-w-2xl text-balance font-display text-3xl font-semibold tracking-tight text-ink sm:text-4xl">
            Find the right loan for you
        </h1>
        <p class="mt-3 max-w-xl text-ink-muted">
            Every product below is matched to suitable lenders based on your income, credit profile and location.
        </p>

        @if ($loanProducts->isEmpty())
            <x-ui.alert tone="accent" class="mt-10">
                Loan products are being added. Check back shortly.
            </x-ui.alert>
        @else
            <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($loanProducts as $product)
                    <a href="{{ route('loans.show', $product) }}" class="group">
                        <x-ui.card class="h-full transition-shadow group-hover:shadow-md">
                            <x-ui.badge tone="accent">{{ $product->category->getLabel() }}</x-ui.badge>
                            <p class="mt-3 font-display text-xl font-semibold text-ink group-hover:text-accent">{{ $product->name }}</p>
                            @if ($product->summary)
                                <p class="mt-2 text-sm text-ink-muted">{{ $product->summary }}</p>
                            @endif
                            <span class="mt-4 inline-flex items-center gap-1 text-sm font-medium text-accent">
                                View details
                                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" class="h-3.5 w-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8h10m0 0-4-4m4 4-4 4" /></svg>
                            </span>
                        </x-ui.card>
                    </a>
                @endforeach
            </div>
        @endif
    </section>
</x-layouts.app>
