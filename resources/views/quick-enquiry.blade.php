<x-layouts.app :title="$content['meta_title']" :description="$content['meta_description']">
    @php
        /*
            Every string and list below comes from QuickEnquiryPageContent
            (Admin → Website Settings → Quick Enquiry Page), which falls back to
            the built-in wording. Any list an admin empties hides its block.
        */
        $visibleLenders = $lenders->take(8);

        /*
            Written out in full rather than interpolated: Tailwind builds classes
            by scanning source text, so a runtime-assembled name never exists.
        */
        $stepColumns = [1 => 'sm:grid-cols-1', 2 => 'sm:grid-cols-2', 3 => 'sm:grid-cols-3', 4 => 'sm:grid-cols-2 xl:grid-cols-4'];
        $exploreColumns = [1 => 'sm:grid-cols-1', 2 => 'sm:grid-cols-2'];
    @endphp

    {{--
        Same shape as the loan page hero (x-site.loan-enquiry): the reason to
        enquire on the left, the form on the right so it is on screen without
        scrolling. Content comes first in the DOM, so on a phone the copy stacks
        above the form.
    --}}
    <section class="relative overflow-hidden border-b border-line bg-surface-2" data-ai-context="Quick Loan Enquiry">
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            <div class="absolute -left-24 -top-32 h-80 w-80 rounded-full bg-accent/10 blur-3xl"></div>
            <div class="absolute -bottom-32 right-1/4 h-72 w-72 rounded-full bg-pass/10 blur-3xl"></div>
            <div class="absolute inset-0 [background-image:radial-gradient(var(--color-line-strong)_1px,transparent_1px)] [background-size:28px_28px] [mask-image:radial-gradient(ellipse_70%_60%_at_20%_0%,black,transparent)] opacity-40"></div>
        </div>

        <div class="relative mx-auto grid max-w-7xl gap-10 px-6 py-8 lg:grid-cols-[minmax(0,1fr)_28rem] lg:items-start lg:gap-14 lg:px-8 lg:py-12">
            <div>
                <x-ui.breadcrumbs :trail="[$content['badge'] => null]" />

                <x-ui.badge tone="accent" class="mt-5">{{ $content['badge'] }}</x-ui.badge>

                <h1 data-reveal="up" class="mt-3 text-balance font-display text-3xl font-semibold leading-[1.1] tracking-tight text-ink sm:text-4xl lg:text-[2.75rem]">
                    {{ $content['heading'] }}
                    <span class="text-accent">{{ $content['heading_accent'] }}</span>
                </h1>

                <p data-reveal="up delay-1" class="mt-4 max-w-xl text-base text-ink-muted">
                    {{ $content['description'] }}
                </p>

                @if ($content['assurances'])
                    <ul data-reveal="up delay-1" class="mt-6 flex flex-wrap gap-x-6 gap-y-2.5">
                        @foreach ($content['assurances'] as $assurance)
                            <li class="flex items-center gap-2 text-sm text-ink">
                                <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-md bg-pass-soft text-pass" aria-hidden="true">
                                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7" />
                                    </svg>
                                </span>
                                {{ $assurance['text'] }}
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($content['steps'])
                    <ol data-reveal="up delay-2" class="mt-8 grid gap-3 {{ $stepColumns[count($content['steps'])] ?? $stepColumns[4] }}">
                        @foreach ($content['steps'] as $step)
                            <li class="relative rounded-xl border border-line bg-surface p-4 shadow-sm">
                                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent" aria-hidden="true">
                                    {{ $loop->iteration }}
                                </span>
                                <p class="mt-3 text-sm font-semibold text-ink">{{ $step['title'] }}</p>
                                @if (filled($step['body'] ?? null))
                                    <p class="mt-1 text-xs leading-relaxed text-ink-muted">{{ $step['body'] }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @endif

                @if ($visibleLenders->isNotEmpty())
                    <div data-reveal="up delay-2" class="mt-8">
                        <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-[0.14em] text-ink-faint">
                            {{ $content['lenders_label'] }}
                        </p>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            @foreach ($visibleLenders as $lender)
                                <x-ui.lender-logo :lender="$lender" :title="$lender->name" />
                            @endforeach
                            @if ($lenders->count() > $visibleLenders->count())
                                <span class="flex h-11 items-center rounded-full border border-line bg-surface px-3 text-xs font-medium text-ink-muted">
                                    +{{ $lenders->count() - $visibleLenders->count() }} more
                                </span>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            <div data-reveal="zoom delay-1" class="lg:sticky lg:top-6">
                @if ($loanProducts->isEmpty())
                    <x-ui.alert tone="accent" title="Online enquiries are paused">
                        We aren't taking online enquiries right now.
                        <a href="{{ route('contact') }}" class="font-medium underline">Contact us</a> and we'll help you directly.
                    </x-ui.alert>
                @else
                    <x-site.loan-enquiry-form
                        :loan-products="$loanProducts"
                        :selected="$selectedProduct"
                        :eyebrow="$content['form_eyebrow']"
                        :headline="$content['form_headline']"
                        :cta-label="$content['form_cta_label']"
                    />
                @endif
            </div>
        </div>
    </section>

    @if ($content['explore_links'])
        <section class="bg-surface">
            <div class="mx-auto max-w-7xl px-6 py-14 lg:px-8">
                <h2 data-reveal="up" class="font-display text-2xl font-semibold text-ink">{{ $content['explore_heading'] }}</h2>
                <p data-reveal="up" class="mt-2 max-w-xl text-ink-muted">{{ $content['explore_description'] }}</p>

                <div class="mt-8 grid gap-5 {{ $exploreColumns[count($content['explore_links'])] ?? 'sm:grid-cols-2 lg:grid-cols-3' }}">
                    @foreach ($content['explore_links'] as $link)
                        <a href="{{ $link['url'] }}" data-reveal="zoom stagger" class="group">
                            <x-ui.card class="card-lift h-full transition-colors transition-shadow group-hover:bg-accent-soft group-hover:shadow-md">
                                <p class="font-display text-lg font-semibold text-ink group-hover:text-accent">{{ $link['label'] }}</p>
                                @if (filled($link['body'] ?? null))
                                    <p class="mt-2 text-sm text-ink-muted">{{ $link['body'] }}</p>
                                @endif
                                <span class="mt-4 inline-flex items-center gap-1 text-sm font-medium text-accent">
                                    {{ filled($link['link_text'] ?? null) ? $link['link_text'] : 'Go' }}
                                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" class="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8h10m0 0-4-4m4 4-4 4" /></svg>
                                </span>
                            </x-ui.card>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</x-layouts.app>
