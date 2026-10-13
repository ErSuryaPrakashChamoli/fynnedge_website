@props(['offers', 'loanProduct'])

@use('App\Support\Formatting\IndianNumberFormatter')
@use('App\Support\Rates\RateBook')
@use('App\Support\Rates\RateFormat')
@use('App\Enums\RateUnit')

@php
    // A hybrid-structured product (Flexi Hybrid Term Loan) says "Available on
    // request" for an unset term instead of a bare dash. Its initial and
    // subsequent tenure split is shown by the hero and calculator, not here.
    $isHybrid = $loanProduct->category->isHybridRepayment();
    $missing = $isHybrid ? 'Available on request' : '—';

    // Every rate in this chart comes from RateBook: published, verified and
    // inside its validity dates right now. An offer without one says "Check
    // current rates" — never an unverified or lapsed figure. The first quote
    // per offer is the one for all borrowers where there is one.
    $quotes = $offers->mapWithKeys(fn ($offer) => [$offer->id => RateBook::quotesFor($offer->id)]);
    $quoteFor = fn ($offer) => $quotes[$offer->id][0] ?? null;
    $headline = RateBook::headlineFor($loanProduct);
    $isCard = $loanProduct->category->rateUnit() === RateUnit::PerMonth;

    // Lowest rate first, offers without a live rate last, so the chart reads
    // best-first even before Alpine boots (or with JS off).
    $offers = $offers->sortBy(fn ($offer) => [
        $quoteFor($offer) === null ? 1 : 0,
        $quoteFor($offer)?->startingRate() ?? 0,
        $offer->lender->name,
    ])->values();

    $rates = $offers->map($quoteFor)->filter()->map(fn ($quote) => $quote->startingRate());
    $maxAmounts = $offers->pluck('max_amount')->filter()->map(fn ($amount) => (float) $amount);
    $maxTenures = $offers->pluck('max_tenure_months')->filter()->map(fn ($months) => (int) $months);

    // Each highlight is only awarded once at least two offers actually have
    // that figure configured — "best of one" is not a comparison, and an
    // offer marked "Available on request" is never guessed at. "Lowest rate"
    // only counts rates open to all borrowers, so a women-only or
    // employer-specific rate never wears it.
    $openRates = $offers->map($quoteFor)->filter(fn ($quote) => $quote?->isForAllBorrowers())->map(fn ($quote) => $quote->startingRate());
    $lowestRate = $openRates->count() > 1 ? $openRates->min() : null;
    $highestAmount = $maxAmounts->count() > 1 ? $maxAmounts->max() : null;
    $longestTenure = $maxTenures->count() > 1 ? $maxTenures->max() : null;

    $lenderTypes = $offers->map(fn ($offer) => $offer->lender->type)->filter()->unique()->values();

    $tenureLabel = function (?int $min, ?int $max): ?string {
        if (! $min && ! $max) {
            return null;
        }

        $inYears = ($min ?? 0) % 12 === 0 && ($max ?? 0) % 12 === 0;

        return $inYears
            ? ($min / 12).'–'.($max / 12).' yrs'
            : "{$min}–{$max} mo";
    };

    $applyUrl = route('loans.apply', $loanProduct);
@endphp

@if ($offers->isNotEmpty())
    <div
        {{ $attributes->class('mt-12') }}
        data-ai-context="Lender Comparison"
        x-data="{
            sort: 'rate',
            type: 'all',
            selected: [],
            comparing: false,
            maxCompare: 4,
            toggle(id) {
                if (this.selected.includes(id)) {
                    this.selected = this.selected.filter((picked) => picked !== id);
                } else if (this.selected.length < this.maxCompare) {
                    this.selected = [...this.selected, id];
                }

                if (this.selected.length < 2) {
                    this.comparing = false;
                }
            },
            clearCompare() {
                this.selected = [];
                this.comparing = false;
            },
            apply() {
                const body = this.$refs.rows;
                const key = this.sort;
                const rows = [...body.querySelectorAll('tr[data-lender-row]')];

                rows.sort((a, b) => key === 'name'
                    ? a.dataset.name.localeCompare(b.dataset.name)
                    : key === 'rate'
                        ? Number(a.dataset.rate) - Number(b.dataset.rate)
                        : Number(b.dataset[key]) - Number(a.dataset[key]));

                rows.forEach((row) => {
                    row.hidden = this.comparing
                        ? ! this.selected.includes(row.dataset.id)
                        : this.type !== 'all' && row.dataset.type !== this.type;
                    body.appendChild(row);
                });
            },
        }"
        x-effect="sort; type; comparing; selected; apply()"
    >
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="font-mono text-[0.68rem] font-semibold uppercase tracking-wider text-accent">Lenders offering this product</p>
                <h2 data-reveal="right" class="mt-1 font-display text-2xl font-semibold text-ink">Compare lenders side by side</h2>
                <p class="mt-1 text-sm text-ink-muted">Tick up to 4 lenders to compare them head to head, or apply with any lender directly.</p>
            </div>
        </div>

        {{-- Headline figures across every lender in the chart. --}}
        <dl class="mt-6 grid grid-cols-2 gap-px overflow-hidden rounded-2xl border border-line bg-line lg:grid-cols-4">
            <div class="bg-surface px-5 py-4">
                <dt class="font-mono text-[0.65rem] uppercase tracking-wider text-ink-faint">Lenders compared</dt>
                <dd class="mt-1 font-display text-2xl font-semibold text-ink">{{ $offers->count() }}</dd>
            </div>
            <div class="bg-surface px-5 py-4">
                <dt class="font-mono text-[0.65rem] uppercase tracking-wider text-ink-faint">{{ $isCard ? 'Finance charges from' : 'Rates from' }}</dt>
                @if ($headline)
                    <dd class="mt-1 font-display text-2xl font-semibold text-pass" title="{{ $headline->backingNote() }}">
                        {{ $headline->startingLabel() }}
                        <span class="font-sans text-xs font-normal text-ink-faint">{{ $headline->unit()->suffix() }}</span>
                    </dd>
                @else
                    <dd class="mt-1 text-sm font-semibold text-ink-muted">Check current rates</dd>
                @endif
            </div>
            <div class="bg-surface px-5 py-4">
                <dt class="font-mono text-[0.65rem] uppercase tracking-wider text-ink-faint">Loan up to</dt>
                <dd class="mt-1 font-display text-2xl font-semibold text-ink">
                    {{ $maxAmounts->isNotEmpty() ? '₹'.IndianNumberFormatter::compact($maxAmounts->max()) : $missing }}
                </dd>
            </div>
            <div class="bg-surface px-5 py-4">
                <dt class="font-mono text-[0.65rem] uppercase tracking-wider text-ink-faint">Tenure up to</dt>
                <dd class="mt-1 font-display text-2xl font-semibold text-ink">
                    {{ $maxTenures->isNotEmpty() ? ($maxTenures->max() % 12 === 0 ? ($maxTenures->max() / 12).' yrs' : $maxTenures->max().' mo') : $missing }}
                </dd>
            </div>
        </dl>

        {{-- Sort + filter toolbar. Server order is already "lowest rate", so
             with JS off the chart still reads best-first. --}}
        <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2" role="group" aria-label="Sort lenders">
                <span class="text-xs font-medium text-ink-faint">Sort by</span>
                @foreach (['rate' => $isCard ? 'Finance charge' : 'Interest rate', 'amount' => 'Loan amount', 'tenure' => 'Tenure', 'name' => 'A–Z'] as $key => $label)
                    <button
                        type="button"
                        x-on:click="sort = '{{ $key }}'"
                        x-bind:aria-pressed="sort === '{{ $key }}'"
                        x-bind:class="sort === '{{ $key }}' ? 'bg-accent text-surface border-accent' : 'bg-surface text-ink-muted border-line hover:border-accent hover:text-ink'"
                        class="rounded-full border px-3 py-1.5 text-xs font-medium transition-colors"
                    >{{ $label }}</button>
                @endforeach
            </div>
            @if ($lenderTypes->count() > 1)
                <div x-show="! comparing" class="flex flex-wrap items-center gap-2" role="group" aria-label="Filter by lender type">
                    <span class="text-xs font-medium text-ink-faint">Show</span>
                    @foreach (['all' => 'All'] + $lenderTypes->mapWithKeys(fn ($type) => [$type->value => $type->getLabel()])->all() as $key => $label)
                        <button
                            type="button"
                            x-on:click="type = '{{ $key }}'"
                            x-bind:aria-pressed="type === '{{ $key }}'"
                            x-bind:class="type === '{{ $key }}' ? 'bg-accent-soft text-accent border-accent' : 'bg-surface text-ink-muted border-line hover:border-accent hover:text-ink'"
                            class="rounded-full border px-3 py-1.5 text-xs font-medium transition-colors"
                        >{{ $label }}</button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Compare bar: appears once a lender is ticked. "Compare" narrows the
             chart to just the picked lenders; nothing is hidden server-side. --}}
        <div
            x-cloak
            x-show="selected.length > 0"
            x-transition.opacity
            class="sticky top-20 z-30 mt-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-accent bg-surface px-5 py-3 shadow-lg"
        >
            <p class="text-sm text-ink">
                <span class="font-semibold" x-text="selected.length + ' of ' + maxCompare + ' lenders selected'"></span>
                <span class="text-ink-muted" x-show="selected.length < 2">— pick at least one more to compare.</span>
                <span class="text-ink-muted" x-show="comparing">— showing only your picks.</span>
            </p>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" x-on:click="clearCompare()" class="rounded-full px-3 py-1.5 text-xs font-medium text-ink-muted hover:text-ink">Clear</button>
                <button
                    type="button"
                    x-show="comparing"
                    x-on:click="comparing = false"
                    class="rounded-full border border-line px-4 py-1.5 text-xs font-semibold text-ink hover:border-accent"
                >Show all lenders</button>
                <button
                    type="button"
                    x-show="! comparing"
                    x-on:click="comparing = true"
                    x-bind:disabled="selected.length < 2"
                    class="rounded-full bg-accent px-4 py-1.5 text-xs font-semibold text-surface transition-opacity disabled:cursor-not-allowed disabled:opacity-40"
                >Compare selected</button>
            </div>
        </div>

        {{-- `relative` is load-bearing: the "Apply" <th> holds a .sr-only span,
             which is position:absolute. Without a positioned ancestor here its
             containing block is the page itself, so it escaped this scroll
             container and stretched the document to the table's full width
             (measured: +396px at 375px wide).

             Below md the same table renders as one stacked card per lender
             (rows become 2-column grids, the header row is hidden and each cell
             carries its own md:hidden label) instead of a 960px table the
             visitor has to scroll sideways. It stays one table so the Alpine
             sort/filter/compare above keeps working on `tr[data-lender-row]`;
             `row.hidden` still wins over max-md:grid because Tailwind's base
             layer forces `[hidden]` to display: none !important. --}}
        <div class="relative mt-4 md:overflow-x-auto md:rounded-2xl md:border md:border-line md:bg-surface md:shadow-sm">
            <table class="w-full border-collapse text-left text-sm max-md:block md:min-w-[960px]">
                <thead class="max-md:hidden">
                    <tr class="bg-accent text-[0.68rem] font-semibold uppercase tracking-wider text-surface">
                        <th scope="col" class="sticky left-0 z-20 bg-accent px-5 py-4">Lender</th>
                        <th scope="col" class="px-4 py-4">{{ $isCard ? 'Finance charge' : 'Interest rate' }}</th>
                        <th scope="col" class="px-4 py-4">Loan amount</th>
                        <th scope="col" class="px-4 py-4">Tenure</th>
                        <th scope="col" class="px-4 py-4">Processing fee</th>
                        <th scope="col" class="px-4 py-4">Eligibility</th>
                        <th scope="col" class="px-4 py-4"><span class="sr-only">Apply</span></th>
                    </tr>
                </thead>
                <tbody x-ref="rows" class="divide-y divide-line max-md:flex max-md:flex-col max-md:gap-3 max-md:divide-y-0">
                    @foreach ($offers as $offer)
                        @php
                            $quote = $quoteFor($offer);
                            $otherQuotes = array_slice($quotes[$offer->id], 1);
                            $rate = $quote?->startingRate();
                            $maxAmount = $offer->max_amount !== null ? (float) $offer->max_amount : null;
                            $maxTenure = $offer->max_tenure_months;
                            $isLowestRate = $lowestRate !== null && $quote?->isForAllBorrowers() && $rate === $lowestRate;
                            $isHighestAmount = $highestAmount !== null && $maxAmount === $highestAmount;
                            $isLongestTenure = $longestTenure !== null && (int) $maxTenure === $longestTenure;
                            $hasFeeDetails = $offer->processingFeeDisplay() !== null || filled($offer->publicFeeNote());

                            // Rate meter: the lowest rate fills the track, the
                            // highest fills a quarter — longer reads as better.
                            $rateSpread = $rates->max() - $rates->min();
                            $rateFill = $rate === null ? 0 : ($rateSpread > 0 ? 25 + 75 * ($rates->max() - $rate) / $rateSpread : 100);
                            $amountFill = $maxAmount === null || $maxAmounts->isEmpty() ? 0 : max(8, 100 * $maxAmount / $maxAmounts->max());
                        @endphp
                        <tr
                            data-lender-row
                            data-id="{{ $offer->id }}"
                            data-name="{{ $offer->lender->name }}"
                            data-type="{{ $offer->lender->type?->value }}"
                            data-rate="{{ $rate ?? 999 }}"
                            data-amount="{{ $maxAmount ?? 0 }}"
                            data-tenure="{{ $maxTenure ?? 0 }}"
                            @class([
                                'group align-top transition-colors hover:bg-accent-soft/50',
                                'max-md:grid max-md:grid-cols-2 max-md:gap-x-4 max-md:gap-y-4 max-md:rounded-2xl max-md:border max-md:bg-surface max-md:p-4 max-md:shadow-sm',
                                'max-md:border-pass' => $isLowestRate,
                                'max-md:border-line' => ! $isLowestRate,
                            ])
                        >
                            <th scope="row" class="bg-surface px-5 py-4 font-normal shadow-[inset_3px_0_0_transparent] transition-shadow group-hover:bg-accent-soft group-hover:shadow-[inset_3px_0_0_var(--color-accent)] max-md:col-span-2 max-md:bg-transparent max-md:p-0 max-md:shadow-none max-md:group-hover:bg-transparent max-md:group-hover:shadow-none md:sticky md:left-0 md:z-10 {{ $isLowestRate ? 'shadow-[inset_3px_0_0_var(--color-pass)]' : '' }}">
                                <div class="flex items-center gap-3">
                                    <label class="flex shrink-0 cursor-pointer items-center" title="Add to compare">
                                        <input
                                            type="checkbox"
                                            class="h-4 w-4 cursor-pointer rounded border-line-strong accent-[var(--color-accent)]"
                                            x-bind:checked="selected.includes('{{ $offer->id }}')"
                                            x-bind:disabled="! selected.includes('{{ $offer->id }}') && selected.length >= maxCompare"
                                            x-on:change="toggle('{{ $offer->id }}'); $event.target.checked = selected.includes('{{ $offer->id }}')"
                                        >
                                        <span class="sr-only">Compare {{ $offer->lender->name }}</span>
                                    </label>
                                    <x-ui.lender-logo :lender="$offer->lender" size="md" />
                                    <div class="min-w-0">
                                        <a href="{{ $applyUrl }}" class="block font-semibold text-ink hover:text-accent md:whitespace-nowrap">
                                            {{ $offer->lender->name }}
                                        </a>
                                        @if ($offer->lender->type)
                                            <span class="font-mono text-[0.62rem] uppercase tracking-wider text-ink-faint">{{ $offer->lender->type->getLabel() }}</span>
                                        @endif
                                    </div>
                                </div>
                                @if ($isLowestRate || $isHighestAmount || $isLongestTenure || $quote?->featured)
                                    <div class="mt-2 flex flex-wrap gap-1">
                                        @if ($quote?->featured)
                                            <x-ui.badge tone="accent" class="px-2! py-0.5! text-[0.58rem]!">Featured</x-ui.badge>
                                        @endif
                                        @if ($isLowestRate)
                                            <x-ui.badge tone="pass" class="px-2! py-0.5! text-[0.58rem]!">Lowest rate</x-ui.badge>
                                        @endif
                                        @if ($isHighestAmount)
                                            <x-ui.badge tone="accent" class="px-2! py-0.5! text-[0.58rem]!">Highest amount</x-ui.badge>
                                        @endif
                                        @if ($isLongestTenure)
                                            <x-ui.badge tone="warn" class="px-2! py-0.5! text-[0.58rem]!">Longest tenure</x-ui.badge>
                                        @endif
                                    </div>
                                @endif
                            </th>
                            <td class="px-4 py-4 max-md:p-0">
                                <p class="mb-1 font-mono text-[0.6rem] uppercase tracking-wider text-ink-faint md:hidden">{{ $isCard ? 'Finance charge' : 'Interest rate' }}</p>
                                @if ($quote)
                                    {{-- The lender's published product rate; a promotional
                                         rate is shown beside it as an offer, never in its place. --}}
                                    <p class="font-display text-xl font-semibold md:whitespace-nowrap {{ $isLowestRate ? 'text-pass' : 'text-ink' }}" data-rate-figure>
                                        {{ RateFormat::percent($quote->rateMin) }}
                                        @if ($quote->rateMax !== null && $quote->rateMax > $quote->rateMin)
                                            <span class="font-sans text-xs font-normal text-ink-faint">– {{ RateFormat::percent($quote->rateMax) }}</span>
                                        @endif
                                    </p>
                                    <div class="mt-2 h-1.5 w-28 max-w-full overflow-hidden rounded-full bg-surface-2" aria-hidden="true">
                                        <div class="h-full rounded-full {{ $isLowestRate ? 'bg-pass' : 'bg-accent' }}" style="width: {{ round($rateFill) }}%"></div>
                                    </div>
                                    <p class="mt-1 text-[0.68rem] text-ink-faint">{{ $quote->unit->suffix() }}{{ $quote->rateMax === null ? ' onwards' : '' }}{{ $quote->repaymentNote() ? ' · '.$quote->repaymentNote() : '' }}</p>
                                    @if ($quote->isPromotional())
                                        <x-ui.badge tone="accent" class="mt-1.5 whitespace-normal px-2! py-0.5! text-[0.6rem]! normal-case! tracking-normal!">{{ $quote->promotionLabel() }}</x-ui.badge>
                                    @endif
                                    @if (! $quote->isForAllBorrowers())
                                        <p class="mt-1 text-[0.68rem] font-medium text-warn">Only for: {{ $quote->borrowerSegment }}</p>
                                    @endif
                                    @if ($quote->eligibilityNotes || $quote->disclaimer)
                                        <p class="mt-1 max-w-56 text-[0.68rem] leading-snug text-ink-faint">{{ collect([$quote->eligibilityNotes, $quote->disclaimer])->filter()->implode(' ') }}</p>
                                    @endif
                                    <p class="mt-1 text-[0.65rem] text-ink-faint">
                                        {{ $quote->verifiedLabel() }}@if ($quote->sourceUrl) · <a href="{{ $quote->sourceUrl }}" target="_blank" rel="nofollow noopener noreferrer" class="underline hover:text-accent">lender's rates</a>@endif
                                    </p>
                                    @if ($otherQuotes)
                                        <details class="mt-1 text-xs text-ink-muted">
                                            <summary class="cursor-pointer font-medium text-accent hover:underline">Other rates ({{ count($otherQuotes) }})</summary>
                                            <ul class="mt-1 space-y-1">
                                                @foreach ($otherQuotes as $other)
                                                    <li>
                                                        <span class="font-medium text-ink">{{ $other->variant ?: ($other->borrowerSegment ?: 'Standard') }}:</span>
                                                        {{ $other->rangeLabel() }}
                                                        @if ($other->variant && $other->borrowerSegment)
                                                            <span class="text-ink-faint">(only for: {{ $other->borrowerSegment }})</span>
                                                        @endif
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </details>
                                    @endif
                                @else
                                    <p class="text-sm font-medium text-ink-muted">Check current rates</p>
                                    <p class="mt-1 text-[0.68rem] text-ink-faint">Rate confirmed by the lender when you apply</p>
                                @endif
                            </td>
                            <td class="px-4 py-4 max-md:p-0">
                                <p class="mb-1 font-mono text-[0.6rem] uppercase tracking-wider text-ink-faint md:hidden">Loan amount</p>
                                @if ($offer->min_amount || $offer->max_amount)
                                    <p class="font-semibold text-ink md:whitespace-nowrap">
                                        ₹{{ IndianNumberFormatter::compact((float) $offer->min_amount) }} – ₹{{ IndianNumberFormatter::compact((float) $offer->max_amount) }}
                                    </p>
                                    <div class="mt-2 h-1.5 w-28 max-w-full overflow-hidden rounded-full bg-surface-2" aria-hidden="true">
                                        <div class="h-full rounded-full bg-accent" style="width: {{ round($amountFill) }}%"></div>
                                    </div>
                                @else
                                    <span class="text-ink-faint">{{ $missing }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 font-semibold text-ink max-md:p-0">
                                <p class="mb-1 font-mono font-normal text-[0.6rem] uppercase tracking-wider text-ink-faint md:hidden">Tenure</p>
                                @if ($tenureLabel($offer->min_tenure_months, $offer->max_tenure_months))
                                    {{ $tenureLabel($offer->min_tenure_months, $offer->max_tenure_months) }}
                                @else
                                    <span class="font-normal text-ink-faint">{{ $missing }}</span>
                                @endif
                            </td>
                            {{-- A card drops a cell it has nothing to say in, rather than label an empty dash. --}}
                            <td @class(['px-4 py-4 max-md:p-0 md:max-w-56', 'max-md:hidden' => ! $hasFeeDetails])>
                                @if ($hasFeeDetails)
                                    <p class="mb-1 font-mono text-[0.6rem] uppercase tracking-wider text-ink-faint md:hidden">Processing fee</p>
                                @endif
                                <p class="font-medium text-ink">{{ $offer->processingFeeDisplay() ?? $missing }}</p>
                                @if ($offer->publicFeeNote())
                                    <details class="mt-1 text-xs text-ink-muted">
                                        <summary class="cursor-pointer font-medium text-accent hover:underline">Fee details</summary>
                                        <p class="mt-1 leading-relaxed">{{ $offer->publicFeeNote() }}</p>
                                    </details>
                                @endif
                            </td>
                            <td @class(['px-4 py-4 max-md:col-span-2 max-md:border-t max-md:border-line max-md:p-0 max-md:pt-3 md:min-w-48', 'max-md:hidden' => ! $offer->eligibilitySummaryPoints()])>
                                @if ($offer->eligibilitySummaryPoints())
                                    <p class="mb-1 font-mono text-[0.6rem] uppercase tracking-wider text-ink-faint md:hidden">Eligibility</p>
                                    <ul class="space-y-1 text-xs text-ink-muted">
                                        @foreach ($offer->eligibilitySummaryPoints() as $point)
                                            <li class="flex items-start gap-1.5">
                                                <span class="text-pass" aria-hidden="true">✓</span>
                                                <span>{{ $point }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    <span class="text-ink-faint">{{ $missing }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 align-middle max-md:col-span-2 max-md:p-0">
                                <x-ui.button tag="a" :href="$applyUrl" size="sm" class="shadow-sm transition-transform group-hover:-translate-y-0.5 max-md:w-full max-md:justify-center">
                                    Apply Now <span aria-hidden="true">→</span>
                                </x-ui.button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-accent-soft px-5 py-4">
            <p class="text-sm text-ink">
                <span class="font-semibold">Not sure which lender fits?</span>
                <span class="text-ink-muted">Check your eligibility with all {{ $offers->count() }} {{ Str::plural('lender', $offers->count()) }} with a single application.</span>
            </p>
            <x-ui.button tag="a" :href="$applyUrl" size="sm">Check eligibility</x-ui.button>
        </div>
        <p class="mt-3 text-xs text-ink-faint">
            Rates are each lender's own published figures, checked against the lender's website on the date shown.
            The rate, amount and approval you are offered depend on the lender's assessment of your profile — FynnEdge does not guarantee approval, disbursal or any particular rate.
        </p>
    </div>
@endif
