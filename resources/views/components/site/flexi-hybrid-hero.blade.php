@props(['loanProduct'])

@php
    use App\Support\Enquiries\EnquiryFormContent;

    $enquiryContent = EnquiryFormContent::forLoanPage($loanProduct->name);
    $lenderOffers = $loanProduct->lenderProducts;
    $initialTenureMonths = $loanProduct->default_initial_tenure_months;
    $totalTenureMonths = $loanProduct->default_tenure_months;
    $subsequentTenureMonths = ($initialTenureMonths && $totalTenureMonths && $totalTenureMonths > $initialTenureMonths)
        ? $totalTenureMonths - $initialTenureMonths
        : null;
@endphp

{{--
    The Flexi Hybrid Term Loan marquee — must render as the very first
    element on loans/show-flexi-hybrid.blade.php, with nothing else between
    it and <x-site.header> in the layout, so it lands immediately below the
    header/nav in real DOM order (no absolute positioning or negative
    margins). Column reflow between mobile and desktop uses grid `order`
    utilities on a single shared markup order, not duplicated markup, so the
    DOM order stays identical at every breakpoint — only the visual order
    (governed by `order-*`) changes.

    `.flexi-hybrid-panel` (resources/css/app.css) scopes the same deep
    slate-navy palette the footer uses to this section — every `bg-surface`,
    `text-ink`, `text-accent`, `border-line` utility below resolves through
    those redeclared CSS variables, so the whole banner reads as one bold,
    premium, standalone panel instead of a plain content section, regardless
    of the site's light/dark mode.
--}}
<section class="flexi-hybrid-panel relative overflow-hidden bg-surface text-ink">
    <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
        <div class="absolute -top-40 left-1/2 h-[36rem] w-[36rem] -translate-x-1/2 rounded-full bg-accent/20 blur-3xl"></div>
        <div class="absolute -bottom-32 -right-24 h-96 w-96 rounded-full bg-accent/10 blur-3xl"></div>
        <div class="absolute inset-0 [background-image:radial-gradient(var(--color-line)_1px,transparent_1px)] [background-size:32px_32px] [mask-image:radial-gradient(ellipse_70%_60%_at_50%_0%,black,transparent)] opacity-50"></div>
    </div>

    <div class="mx-auto grid max-w-7xl gap-x-12 gap-y-8 px-6 py-12 lg:grid-cols-[1.4fr_26rem] lg:items-center lg:px-8 lg:py-14">
        <p data-reveal="up" class="order-1 col-start-1 inline-flex w-fit items-center gap-2 rounded-full border border-line-strong bg-surface-2 px-4 py-1.5 font-mono text-xs font-semibold uppercase tracking-[0.16em] text-accent shadow-sm">
            Flexi Hybrid Term Loan
        </p>

        <h1 data-reveal="up delay-1" class="order-2 col-start-1 mt-4 text-balance font-display text-4xl font-semibold leading-[1.05] tracking-tight text-ink sm:text-5xl lg:text-6xl">
            Lower Initial EMI. Flexible Access. <span class="text-accent">Smarter Repayment.</span>
        </h1>

        <p data-reveal="up delay-2" class="order-3 col-start-1 max-w-xl text-lg text-ink-muted sm:text-xl">
            Compare Flexi Hybrid Term Loan options from {{ $lenderOffers->pluck('lender.name')->join(', ', ' and ') ?: 'FynnEdge\'s lending partners' }}, understand your initial and subsequent repayment structure, and apply through FynnEdge.
        </p>

        <div data-reveal="zoom delay-2" class="order-4 col-start-1 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div class="rounded-2xl border border-line bg-surface-2 p-4">
                <p class="font-display text-2xl font-semibold text-ink">{{ $lenderOffers->count() }}</p>
                <p class="mt-0.5 font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">Lenders</p>
            </div>
            <div class="rounded-2xl border border-line bg-surface-2 p-4">
                <p class="font-display text-2xl font-semibold text-ink">{{ $initialTenureMonths ?? '—' }} mo</p>
                <p class="mt-0.5 font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">Initial tenure</p>
            </div>
            <div class="rounded-2xl border border-line bg-surface-2 p-4">
                <p class="font-display text-2xl font-semibold text-ink">{{ $subsequentTenureMonths ?? '—' }} mo</p>
                <p class="mt-0.5 font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">Subsequent tenure</p>
            </div>
            <div class="rounded-2xl border border-line bg-surface-2 p-4">
                <p class="font-display text-lg font-semibold text-ink">Flexible</p>
                <p class="mt-0.5 font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">Repayment*</p>
            </div>
        </div>

        @if ($lenderOffers->isNotEmpty())
            <div data-reveal="fade" class="order-5 col-start-1">
                <p class="font-mono text-[0.7rem] font-semibold uppercase tracking-[0.16em] text-ink-faint">Available through</p>
                <div class="mt-3 flex flex-wrap items-center gap-5">
                    @foreach ($lenderOffers as $offer)
                        <div class="flex items-center gap-2">
                            <x-ui.lender-logo :lender="$offer->lender" size="sm" />
                            <span class="font-display text-base font-semibold text-ink">{{ $offer->lender->name }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div data-reveal="up delay-3" class="order-7 col-start-1 flex flex-col gap-3 sm:flex-row">
            <x-ui.button tag="a" :href="route('loans.apply', $loanProduct)" size="lg" class="w-full justify-center text-base sm:w-auto">
                {{ $loanProduct->cta_label ?: 'Apply Now' }}
            </x-ui.button>
            <x-ui.button tag="a" href="#flexi-hybrid-details" variant="secondary" size="lg" class="w-full justify-center text-base sm:w-auto">
                View Details
            </x-ui.button>
        </div>

        {{--
            The enquiry form holds this column, so this page opens with a form on
            screen like every other loan page. What used to sit here — the
            two-stage "initial tenure / subsequent tenure" card — is not lost:
            <x-site.repayment-stages> renders that same breakdown in full further
            down the page, and the four stat tiles on the left still quote both
            tenures above the fold.
        --}}
        <div data-reveal="right" class="order-6 col-start-1 lg:order-none lg:col-start-2 lg:row-span-7 lg:row-start-1 lg:self-center">
            <x-site.loan-enquiry-form :loan-product="$loanProduct" :eyebrow="$enquiryContent['eyebrow']" :cta-label="$enquiryContent['ctaLabel']" />
        </div>
    </div>

    <p class="mx-auto max-w-7xl px-6 pb-8 text-xs text-ink-faint lg:px-8">*Subject to applicable lender/product terms — see the comparison and calculator below for lender-specific figures.</p>
</section>
