<x-layouts.app title="Check Your Eligibility" description="Tell us what you're looking to finance and we'll take you through a short profile to check suitable lenders.">
    <section class="mx-auto max-w-4xl px-6 py-14 lg:px-8">
        <x-ui.breadcrumbs :trail="['Check Your Eligibility' => null]" />

        <h1 class="mt-5 text-balance font-display text-3xl font-semibold tracking-tight text-ink sm:text-4xl">
            What are you looking to finance?
        </h1>
        <p class="mt-3 max-w-xl text-ink-muted">
            Pick a product to start — it takes a few minutes to tell us about yourself.
        </p>

        @if ($loanProducts->isEmpty())
            <x-ui.alert tone="accent" class="mt-10">
                Applications aren't open yet. Check back shortly.
            </x-ui.alert>
        @else
            <div class="mt-10 grid gap-5 sm:grid-cols-2">
                @foreach ($loanProducts as $product)
                    <a href="{{ route('loans.apply', $product) }}" class="group">
                        <x-ui.card class="h-full transition-shadow group-hover:shadow-md">
                            <x-ui.badge tone="accent">{{ $product->category->getLabel() }}</x-ui.badge>
                            <p class="mt-3 font-display text-lg font-semibold text-ink group-hover:text-accent">{{ $product->name }}</p>
                            @if ($product->summary)
                                <p class="mt-2 text-sm text-ink-muted">{{ $product->summary }}</p>
                            @endif
                            <span class="mt-4 inline-flex items-center gap-1 text-sm font-medium text-accent">
                                Start
                                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" class="h-3.5 w-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8h10m0 0-4-4m4 4-4 4" /></svg>
                            </span>
                        </x-ui.card>
                    </a>
                @endforeach
            </div>
        @endif
    </section>
</x-layouts.app>
