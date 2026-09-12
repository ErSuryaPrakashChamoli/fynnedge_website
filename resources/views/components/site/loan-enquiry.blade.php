@props([
    'loanProduct',
    'label' => null,
    'description' => null,
    'breadcrumbs' => null,
    'eyebrow' => null,
])

@php
    /*
        The hero band on every loan page: product content on the left, the
        enquiry form on the right, sized so the form is fully visible without
        scrolling on a normal laptop viewport. On a phone the columns stack —
        content first, form directly beneath it.

        Everything on the left is read from the LoanProduct row, so a new loan
        page needs no new markup:

            <x-site.loan-enquiry :loan-product="$loanProduct" />

        The copy (heading, description, form label, button) is admin-editable
        through Marketing Sections → "Loan pages — enquiry form"; see
        App\Support\Enquiries\EnquiryFormContent. `$label` is what :product
        resolves to — the product name, or a landing page's own title.

        Each block is omitted rather than rendered empty when the product has
        not been given that field, so a sparsely filled product still looks
        deliberate.
    */
    use App\Support\Enquiries\EnquiryFormContent;
    use App\Support\Formatting\IndianNumberFormatter;

    $label = $label ?: $loanProduct->name;
    $content = EnquiryFormContent::forLoanPage($label, $description ?: $loanProduct->summary);

    $highlights = array_slice(array_filter($loanProduct->benefits ?: $loanProduct->features ?: []), 0, 3);

    $rate = $loanProduct->min_interest_rate
        ? rtrim(rtrim(number_format((float) $loanProduct->min_interest_rate, 2), '0'), '.').'%'
        : null;

    $tenure = $loanProduct->max_tenure_months
        ? ($loanProduct->max_tenure_months >= 24
            ? round($loanProduct->max_tenure_months / 12).' years'
            : $loanProduct->max_tenure_months.' months')
        : null;

    /*
        Written out in full rather than interpolated (grid-cols-{{ $n }}):
        Tailwind generates utilities by scanning source text, so a class name
        assembled at runtime is never built and the grid silently collapses.
    */
    $statColumns = [1 => 'grid-cols-1', 2 => 'grid-cols-2', 3 => 'grid-cols-3'];

    $stats = array_filter([
        $rate ? ['label' => 'Interest from', 'value' => $rate] : null,
        $loanProduct->max_amount ? ['label' => 'Loan up to', 'value' => '₹'.IndianNumberFormatter::compact($loanProduct->max_amount)] : null,
        $tenure ? ['label' => 'Tenure up to', 'value' => $tenure] : null,
    ]);
@endphp

<section {{ $attributes->class('relative overflow-hidden border-b border-line bg-surface-2') }} data-ai-context="{{ $label }} Enquiry">
    <div class="pointer-events-none absolute inset-0" aria-hidden="true">
        <div class="absolute -left-24 -top-32 h-80 w-80 rounded-full bg-accent/10 blur-3xl"></div>
        <div class="absolute -bottom-32 right-1/3 h-72 w-72 rounded-full bg-pass/10 blur-3xl"></div>
        <div class="absolute inset-0 [background-image:radial-gradient(var(--color-line-strong)_1px,transparent_1px)] [background-size:28px_28px] [mask-image:radial-gradient(ellipse_70%_60%_at_20%_0%,black,transparent)] opacity-40"></div>
    </div>

    {{-- lg:items-start, not items-center: the form card is taller than the copy
         beside it, and centring would push the copy down into dead space. --}}
    <div class="relative mx-auto grid max-w-7xl gap-8 px-6 py-8 lg:grid-cols-[minmax(0,1fr)_26rem] lg:items-start lg:gap-12 lg:px-8 lg:py-10">
        {{-- Left: what this loan is. Content first in the DOM, so it is also
             first when the columns stack on a phone. --}}
        <div>
            {{ $breadcrumbs }}

            <x-ui.badge tone="accent" :class="$breadcrumbs ? 'mt-5' : ''" itemprop="category">{{ $loanProduct->category->getLabel() }}</x-ui.badge>

            @if ($eyebrow)
                <p class="mt-3 font-display text-sm font-semibold uppercase tracking-wide text-accent">{{ $eyebrow }}</p>
            @endif

            <h1 itemprop="name" class="mt-3 text-balance font-display text-3xl font-semibold leading-[1.1] tracking-tight text-ink sm:text-4xl lg:text-[2.75rem]">
                {{ $content['heading'] }}
            </h1>

            @if ($content['description'])
                <p itemprop="description" class="mt-3 max-w-xl text-base text-ink-muted">{{ $content['description'] }}</p>
            @endif

            @if ($highlights)
                <ul class="mt-6 space-y-2.5">
                    @foreach ($highlights as $highlight)
                        <li class="flex items-start gap-3">
                            <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-md bg-accent-soft text-accent" aria-hidden="true">
                                <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7" />
                                </svg>
                            </span>
                            <span class="text-sm text-ink">{{ $highlight }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($stats)
                <div class="mt-6 grid max-w-lg {{ $statColumns[count($stats)] }} divide-x divide-line overflow-hidden rounded-xl border border-line bg-surface">
                    @foreach ($stats as $stat)
                        <div class="px-4 py-3 text-center">
                            <p class="font-display text-lg font-semibold text-ink">{{ $stat['value'] }}</p>
                            <p class="mt-0.5 text-[0.7rem] uppercase tracking-wide text-ink-faint">{{ $stat['label'] }}</p>
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($slot->isNotEmpty())
                <div class="mt-6 flex flex-wrap gap-3">{{ $slot }}</div>
            @endif
        </div>

        {{-- Right: the form. Same component on every loan page. --}}
        <div class="lg:sticky lg:top-6">
            <x-site.loan-enquiry-form
                :loan-product="$loanProduct"
                :eyebrow="$content['eyebrow']"
                :cta-label="$content['ctaLabel']"
            />
        </div>
    </div>
</section>
