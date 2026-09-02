@props(['heading' => 'You could go directly to a bank. But why us?'])

<div {{ $attributes }} data-reveal="fade">
    <p class="font-mono text-xs font-semibold uppercase tracking-[0.14em] text-accent">Why FynnEdge?</p>
    <h2 class="mt-3 max-w-xl text-balance font-display text-2xl font-semibold tracking-tight text-ink sm:text-3xl">
        {{ $heading }}
    </h2>

    <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ([
            ['One profile, many lenders', 'Share your details once — we match you against multiple lenders\' criteria instead of you applying to each one separately.'],
            ['Clear, upfront eligibility', 'See which lenders you\'re likely eligible for, with plain-language reasons, before you apply.'],
            ['A fully online journey', 'Check eligibility, compare lenders and apply — all online, without a branch visit.'],
            ['Free EMI & credit tools', 'Plan your EMI and check your credit score for free, before you commit to anything.'],
            ['Guidance, not just a listing', 'We help you understand your options and why a lender is or isn\'t a fit — not just hand you a list.'],
            ['No service charges', 'Comparing and applying through FynnEdge is free. Any lender fees are disclosed upfront by the lender.'],
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
