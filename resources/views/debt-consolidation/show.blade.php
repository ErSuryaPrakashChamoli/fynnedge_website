<x-layouts.app handles-faqs :title="$page->seoTitle()" :description="$page->seoDescription()" :canonical="$page->seoCanonicalUrl()" :og-image="$page->seoOgImageUrl()" :social="$page->seoSocial()" :robots="$page->seoRobots()" :structured-data="$page->seoStructuredData()" :page-type="$page->seoPageType()" :schema-template="$page->seoSchemaTemplate()" :schema-nodes="$schemaNodes">
    @php
        /*
            Title, intro, body, SEO and FAQs: this page's row in Website Pages →
            General Pages. Shared sections: DebtConsolidationPageContent
            (Website Settings → Debt Consolidation Pages), including which
            sections this page shows. Written out in full so Tailwind sees them.
        */
        $shows = fn (string $section): bool => \App\Support\Pages\DebtConsolidationPageContent::shows($content, $pageKey, $section);
        $routeFor = fn (string $key): string => route(\App\Support\Pages\DebtConsolidationPages::route($key));
        $isLive = fn (string $key): bool => in_array($key, $liveKeys, true);
        $hasForm = in_array($pageKey, ['calculator', 'assessment'], true);
        $showEnquiry = $shows('enquiry') && ! $hasForm && $applicantTypes !== [];
        $trail = $pageKey === 'hub'
            ? [$page->title => null]
            : [__('Debt Consolidation') => $isLive('hub') ? $routeFor('hub') : null, $page->title => null];
        $stepColumns = [1 => 'sm:grid-cols-1', 2 => 'sm:grid-cols-2', 3 => 'sm:grid-cols-3', 4 => 'sm:grid-cols-2 xl:grid-cols-4', 5 => 'sm:grid-cols-2 lg:grid-cols-5'];
    @endphp

    @if ($previewWhileOff)
        <div class="border-b border-line bg-accent-soft">
            <p class="mx-auto max-w-7xl px-6 py-2 text-sm text-ink lg:px-8">{{ __('Preview: Debt Consolidation is switched off, so the enquiry and assessment forms are hidden. They appear once the service is switched on in Website Settings.') }}</p>
        </div>
    @endif

    <section class="relative overflow-hidden border-b border-line bg-surface-2" data-ai-context="Debt Consolidation">
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            <div class="absolute -left-24 -top-32 h-80 w-80 rounded-full bg-accent/10 blur-3xl"></div>
            <div class="absolute -bottom-32 right-1/4 h-72 w-72 rounded-full bg-pass/10 blur-3xl"></div>
        </div>

        <div class="relative mx-auto max-w-7xl px-6 pt-5 lg:px-8 lg:pt-8">
            <x-ui.breadcrumbs :trail="$trail" />
        </div>

        <div @class([
            'relative mx-auto grid max-w-7xl gap-10 px-6 pb-8 pt-4 lg:px-8 lg:pb-12 lg:pt-6',
            'lg:grid-cols-[minmax(0,1fr)_28rem] lg:items-start lg:gap-14' => $showEnquiry,
        ])>
            @if ($showEnquiry)
                <div id="debt-enquiry" class="scroll-mt-24 lg:sticky lg:top-24 lg:order-last">
                    <livewire:debt-consolidation-enquiry :source="$pageKey" :heading="$content['enquiry_heading']" :subheading="$content['enquiry_subheading']" />
                </div>
            @endif

            <div class="min-w-0">
                <span class="inline-flex max-w-full self-start rounded-full bg-accent-soft px-3 py-1 text-xs font-semibold uppercase tracking-wide text-accent">{{ $content['hero_badge'] }}</span>

                <h1 class="mt-3 text-balance font-display text-3xl font-semibold leading-[1.1] tracking-tight text-ink sm:text-4xl lg:text-[2.75rem]">
                    {{ $page->title }}
                </h1>

                @if ($page->excerpt)
                    <p class="mt-4 max-w-2xl text-base text-ink-muted sm:text-lg">{{ $page->excerpt }}</p>
                @endif

                @if (! $hasForm && ($isLive('assessment') || $isLive('calculator')))
                    <div class="mt-6 flex flex-wrap gap-3">
                        @if ($isLive('assessment'))
                            <x-ui.button tag="a" :href="$routeFor('assessment')" size="lg">{{ $content['primary_cta_label'] }}</x-ui.button>
                        @endif
                        @if ($isLive('calculator'))
                            <x-ui.button tag="a" :href="$routeFor('calculator')" variant="secondary" size="lg">{{ $content['secondary_cta_label'] }}</x-ui.button>
                        @endif
                    </div>
                @endif

                @if ($shows('trust') && $content['trust_points'])
                    <ul class="mt-6 flex flex-wrap gap-x-6 gap-y-2.5">
                        @foreach ($content['trust_points'] as $point)
                            <li class="flex items-center gap-2 text-sm text-ink">
                                <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-md bg-pass-soft text-pass" aria-hidden="true">
                                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7" /></svg>
                                </span>
                                {{ $point['text'] }}
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if (count($navigation) > 1)
                    <nav class="mt-8 flex flex-wrap gap-2" aria-label="{{ __('Debt consolidation pages') }}">
                        @foreach ($navigation as $link)
                            <a href="{{ $link['url'] }}" @if ($link['active']) aria-current="page" @endif @class([
                                'rounded-full border px-4 py-1.5 text-sm font-medium transition-colors',
                                'border-accent bg-accent text-white' => $link['active'],
                                'border-line bg-surface text-ink-muted hover:border-accent hover:text-accent' => ! $link['active'],
                            ])>{{ $link['label'] }}</a>
                        @endforeach
                    </nav>
                @endif
            </div>
        </div>
    </section>

    @if ($pageKey === 'calculator')
        <section class="mx-auto max-w-7xl px-6 py-10 lg:px-8">
            <livewire:debt-consolidation-calculator />
        </section>
    @elseif ($pageKey === 'assessment')
        <section class="mx-auto max-w-7xl px-6 py-10 lg:px-8">
            @if ($applicantTypes === [])
                <x-ui.alert tone="warn" :title="__('Online assessments are paused')">
                    {!! __('We are not taking new online assessments right now. <a href=":url" class="font-medium underline">Contact us</a> and an advisor will help you.', ['url' => e(route('contact'))]) !!}
                </x-ui.alert>
            @else
                <livewire:debt-consolidation-application />
            @endif
        </section>
    @endif

    <section class="mx-auto max-w-7xl px-6 py-12 lg:px-8">
        @if ($page->body)
            <div class="rich-text max-w-none text-ink-muted text-justify hyphens-auto">
                {!! $page->body !!}
            </div>
        @endif

        @if ($shows('audiences'))
            <div class="mt-14" data-reveal="fade">
                <h2 class="font-display text-2xl font-semibold text-ink">{{ $content['audiences_heading'] }}</h2>
                <div class="mt-6 grid gap-6 md:grid-cols-2">
                    @foreach ([['key' => 'personal', 'heading' => $content['personal_heading'], 'summary' => $content['personal_summary'], 'points' => $content['personal_points']], ['key' => 'business', 'heading' => $content['business_heading'], 'summary' => $content['business_summary'], 'points' => $content['business_points']]] as $audience)
                        <div class="flex flex-col rounded-2xl border border-line bg-surface p-6 shadow-sm">
                            <h3 class="font-display text-lg font-semibold text-ink">{{ $audience['heading'] }}</h3>
                            <p class="mt-2 text-sm text-ink-muted">{{ $audience['summary'] }}</p>
                            @if ($audience['points'])
                                <ul class="mt-4 space-y-2 text-sm text-ink">
                                    @foreach ($audience['points'] as $point)
                                        <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-accent" aria-hidden="true"></span>{{ $point['text'] }}</li>
                                    @endforeach
                                </ul>
                            @endif
                            @if ($isLive($audience['key']))
                                <a href="{{ $routeFor($audience['key']) }}" class="mt-5 inline-flex items-center gap-1 text-sm font-semibold text-accent hover:underline">{{ __('Learn more') }} <span aria-hidden="true">→</span></a>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @foreach (['personal_points' => ['heading' => $content['personal_heading'], 'summary' => $content['personal_summary']], 'business_points' => ['heading' => $content['business_heading'], 'summary' => $content['business_summary']]] as $listKey => $list)
            @if ($shows($listKey) && $content[$listKey])
                <div class="mt-14 rounded-2xl border border-line bg-surface p-6 shadow-sm" data-reveal="fade">
                    <h2 class="font-display text-xl font-semibold text-ink">{{ __('Debts we can assess') }}</h2>
                    <p class="mt-2 text-sm text-ink-muted">{{ $list['summary'] }}</p>
                    <ul class="mt-4 grid gap-2 text-sm text-ink sm:grid-cols-2">
                        @foreach ($content[$listKey] as $point)
                            <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-accent" aria-hidden="true"></span>{{ $point['text'] }}</li>
                        @endforeach
                    </ul>
                    <p class="mt-4 text-xs text-ink-faint">{{ __('Every debt can be recorded and assessed. Whether a particular loan can be taken over depends on the lender and the financing route.') }}</p>
                </div>
            @endif
        @endforeach

        @if ($shows('steps') && $content['steps'])
            <div class="mt-14" data-reveal="fade">
                <h2 class="font-display text-2xl font-semibold text-ink">{{ $content['steps_heading'] }}</h2>
                <ol class="mt-6 grid gap-4 {{ $stepColumns[count($content['steps'])] ?? $stepColumns[4] }}">
                    @foreach ($content['steps'] as $step)
                        <li class="rounded-xl border border-line bg-surface p-5 shadow-sm">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-accent-soft font-mono text-sm font-semibold text-accent" aria-hidden="true">{{ $loop->iteration }}</span>
                            <p class="mt-3 font-semibold text-ink">{{ $step['title'] }}</p>
                            @if (filled($step['body'] ?? null))
                                <p class="mt-1 text-sm leading-relaxed text-ink-muted">{{ $step['body'] }}</p>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </div>
        @endif

        @if ($shows('tools') && ($isLive('calculator') || $isLive('assessment')))
            <div class="mt-14" data-reveal="fade">
                <h2 class="font-display text-2xl font-semibold text-ink">{{ $content['tools_heading'] }}</h2>
                <div class="mt-6 grid gap-6 md:grid-cols-2">
                    @foreach ([['key' => 'calculator', 'title' => $content['calculator_card_title'], 'body' => $content['calculator_card_body'], 'cta' => $content['secondary_cta_label']], ['key' => 'assessment', 'title' => $content['assessment_card_title'], 'body' => $content['assessment_card_body'], 'cta' => $content['primary_cta_label']]] as $tool)
                        @if ($isLive($tool['key']) && $tool['key'] !== $pageKey)
                            <a href="{{ $routeFor($tool['key']) }}" class="group flex flex-col rounded-2xl border border-line bg-surface p-6 shadow-sm transition-colors hover:border-accent">
                                <h3 class="font-display text-lg font-semibold text-ink">{{ $tool['title'] }}</h3>
                                <p class="mt-2 flex-1 text-sm text-ink-muted">{{ $tool['body'] }}</p>
                                <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-accent group-hover:underline">{{ $tool['cta'] }} <span aria-hidden="true">→</span></span>
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif

        @if ($related->isNotEmpty())
            <div class="mt-14" data-reveal="fade">
                <h2 class="font-display text-xl font-semibold text-ink">{{ $content['related_heading'] }}</h2>
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach ($related as $landingPage)
                        <a href="{{ route('loans.landing-pages.show', ['loanProduct' => $landingPage->loanProduct, 'landingPage' => $landingPage]) }}" class="inline-flex items-center gap-1.5 rounded-full border border-line bg-surface px-4 py-2 text-sm text-ink-muted transition-colors hover:border-accent hover:text-accent">{{ $landingPage->title }}</a>
                    @endforeach
                </div>
            </div>
        @endif

        <x-site.faq-accordion :faqs="$faqs" />
        <x-site.faq-json-ld :faqs="$faqs" />

        @if ($shows('disclaimer'))
            <div class="mt-14 rounded-xl border border-line bg-surface-2 p-5 text-xs leading-relaxed text-ink-muted">
                <p>{{ $disclaimer }}</p>
                @if (in_array($pageKey, ['calculator', 'assessment'], true))
                    <p class="mt-2">{{ $calculatorDisclaimer }}</p>
                @endif
            </div>
        @endif

        <x-site.updated-at :at="$page->updated_at" class="mt-8" />
    </section>
</x-layouts.app>
