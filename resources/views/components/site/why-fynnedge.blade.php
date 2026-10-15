@props(['heading' => null])

<div {{ $attributes }} data-reveal="fade">
    <p class="font-mono text-xs font-semibold uppercase tracking-[0.14em] text-accent">{{ __('Why FynnEdge?') }}</p>
    <h2 class="mt-3 max-w-xl text-balance font-display text-2xl font-semibold tracking-tight text-ink sm:text-3xl">
        {{ $heading ?? __('You could go directly to a bank. But why us?') }}
    </h2>

    <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ([
            [__('One profile, many lenders'), __('Share your details once — we match you against multiple lenders\' criteria instead of you applying to each one separately.')],
            [__('Clear, upfront eligibility'), __('See which lenders you\'re likely eligible for, with plain-language reasons, before you apply.')],
            [__('A fully online journey'), __('Check eligibility, compare lenders and apply — all online, without a branch visit.')],
            [__('Free EMI & credit tools'), __('Plan your EMI and check your credit score for free, before you commit to anything.')],
            [__('Guidance, not just a listing'), __('We help you understand your options and why a lender is or isn\'t a fit — not just hand you a list.')],
            [__('No service charges'), __('Comparing and applying through FynnEdge is free. Any lender fees are disclosed upfront by the lender.')],
        ] as $index => $item)
            <x-ui.card data-reveal="zoom stagger" class="card-lift transition-colors transition-shadow hover:bg-accent-soft hover:shadow-md hover:animate-card-swing">
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-accent-soft font-mono text-xs font-semibold text-accent">
                    {{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}
                </span>
                <p class="mt-4 font-display text-base font-semibold text-ink">{{ $item[0] }}</p>
                <p class="mt-2 text-sm text-ink-muted">{{ $item[1] }}</p>
            </x-ui.card>
        @endforeach
    </div>
</div>
