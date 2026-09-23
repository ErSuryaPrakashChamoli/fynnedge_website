{{-- Part of the per-visitor funnel (PAN + mobile), kept out of search the same way
     as /journey and /applications: robots.txt disallows /credit-score/ and the
     sitemap never lists it (CrawlerPolicy, Sitemap). --}}
<x-layouts.app
    :title="$content['meta_title']"
    :description="$content['meta_description']"
    robots="noindex, nofollow"
>
    <section class="mx-auto max-w-7xl px-6 py-14 lg:px-8">
        <x-ui.breadcrumbs :trail="['Credit Score' => null, $bureau->getLabel() => null]" />

        <div class="mt-6 grid gap-12 lg:grid-cols-[1.1fr_1fr] lg:items-start">
            <div data-reveal="up">
                <x-ui.badge tone="accent">{{ $content['badge'] }}</x-ui.badge>

                <h1 class="mt-4 text-balance font-display text-3xl font-semibold tracking-tight text-ink sm:text-4xl">
                    {{ $content['heading'] }}
                </h1>
                <p class="mt-3 max-w-lg text-ink-muted">
                    {{ $content['description'] }}
                </p>

                @if ($content['benefits'])
                    <div class="mt-8">
                        <p class="font-display text-lg font-semibold text-ink">{{ $content['benefits_heading'] }}</p>
                        <ul class="mt-4 flex flex-col gap-3">
                            @foreach ($content['benefits'] as $benefit)
                                <li class="flex items-start gap-3 text-sm text-ink-muted">
                                    <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-pass-soft text-pass">
                                        <svg viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4L8 11.6l6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" /></svg>
                                    </span>
                                    {{ $benefit['text'] }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($content['stats'])
                    <div @class([
                        'mt-10 grid gap-4 border-t border-line pt-6',
                        'grid-cols-1' => count($content['stats']) === 1,
                        'grid-cols-2' => count($content['stats']) === 2,
                        'grid-cols-3' => count($content['stats']) >= 3,
                    ])>
                        @foreach ($content['stats'] as $stat)
                            <div>
                                <p class="font-display text-lg font-semibold text-ink">{{ $stat['value'] }}</p>
                                @if (filled($stat['label'] ?? null))
                                    <p class="mt-1 text-xs text-ink-faint">{{ $stat['label'] }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div data-reveal="right" class="lg:sticky lg:top-24">
                <livewire:credit-score-check :bureau="$bureau->value" />
            </div>
        </div>
    </section>
</x-layouts.app>
