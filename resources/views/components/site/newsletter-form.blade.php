@props([
    'source' => 'website',
    'sourceUrl' => null,
    'variant' => 'card',
    'heading' => 'Stay ahead of your finances',
    'description' => 'Get practical financial insights, loan tips and useful updates from FynnEdge directly in your inbox.',
    'note' => null,
    'ctaLabel' => 'Subscribe',
    'showName' => false,
])

@php
    /*
        One component, four placements. It renders with the site's existing
        tokens and x-ui.* components only — no newsletter-specific colours,
        fonts or button styles — so it inherits the theme (including the
        per-section appearance overrides from Settings) like everything else.

        The `banner` variant is the loud one: a raised panel with its own
        accent glow and an inset, icon-led email field, used where the signup
        has to compete with a page full of links (the footer).

        Nothing renders at all when the newsletter is switched off in the admin
        panel, so disabling it removes the forms rather than leaving dead ones.
    */
    $enabled = \App\Modules\Newsletter\Services\NewsletterSettings::enabled();
    $status = session('newsletterStatus');
    $error = $errors->first('email');
    $privacyUrl = \Illuminate\Support\Facades\Route::has('privacy-policy') ? route('privacy-policy') : null;
    $resolvedSourceUrl = $sourceUrl ?? request()->path();
    $isBanner = $variant === 'banner';
@endphp

@if ($enabled)
    <div id="newsletter" {{ $attributes->class([
        'rounded-2xl border border-line bg-surface p-6 sm:p-8' => $variant === 'card',
        'rounded-xl border border-line bg-surface-2 p-5' => $variant === 'compact',
        'relative isolate overflow-hidden rounded-3xl border border-line-strong bg-surface-2 p-6 sm:p-8 lg:p-10' => $isBanner,
        '' => $variant === 'inline',
    ]) }}>
        @if ($isBanner)
            {{-- Decorative accent wash — lifts the panel off the surrounding surface. --}}
            <div aria-hidden="true" class="pointer-events-none absolute -right-24 -top-28 -z-10 h-64 w-64 rounded-full bg-accent/20 blur-3xl"></div>
            <div aria-hidden="true" class="pointer-events-none absolute -bottom-32 -left-24 -z-10 h-64 w-64 rounded-full bg-accent/10 blur-3xl"></div>
        @endif

        <div @class(['grid items-center gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,30rem)]' => $isBanner])>
            <div>
                @if ($isBanner)
                    <span class="inline-flex items-center gap-2 rounded-full border border-line-strong bg-accent-soft px-3 py-1 font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-accent">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-3.5 w-3.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                        </svg>
                        Newsletter
                    </span>
                @endif

                @if ($heading)
                    <p @class([
                        'font-display font-semibold text-ink' => true,
                        'text-xl sm:text-2xl' => $variant === 'card',
                        'mt-4 text-2xl sm:text-3xl' => $isBanner,
                        'text-base' => ! $isBanner && $variant !== 'card',
                    ])>{{ $heading }}</p>
                @endif

                @if ($description)
                    <p @class([
                        'mt-2 text-ink-muted' => true,
                        'text-sm sm:text-base' => $variant === 'card',
                        'max-w-md text-sm sm:text-base' => $isBanner,
                        'text-sm' => ! $isBanner && $variant !== 'card',
                    ])>{{ $description }}</p>
                @endif

                @if ($note)
                    <p @class([
                        'text-ink-muted' => true,
                        'mt-1 text-sm sm:text-base' => $isBanner,
                        'mt-1 text-sm' => ! $isBanner,
                    ])>{{ $note }}</p>
                @endif
            </div>

            <div>
                @if ($status)
                    <x-ui.alert class="{{ $isBanner ? 'mb-4' : 'mt-4' }}" tone="pass">{{ $status }}</x-ui.alert>
                @endif

                <form
                    method="POST"
                    action="{{ route('newsletter.subscribe') }}"
                    @class([
                        'flex flex-col gap-3 sm:flex-row sm:items-start' => true,
                        'mt-5' => ! $isBanner,
                        'sm:items-center' => $isBanner,
                    ])
                >
                    @csrf
                    <input type="hidden" name="source" value="{{ $source }}">
                    <input type="hidden" name="source_url" value="{{ $resolvedSourceUrl }}">

                    {{-- Honeypot: hidden from people, irresistible to bots. Never remove without replacing. --}}
                    <div class="hidden" aria-hidden="true">
                        <label for="website-{{ $source }}">Website</label>
                        <input type="text" name="website" id="website-{{ $source }}" tabindex="-1" autocomplete="off">
                    </div>

                    @if ($showName)
                        <x-ui.input name="newsletter_name" type="text" placeholder="Name (optional)" class="w-full sm:w-44" aria-label="Name" />
                    @endif

                    <div class="relative flex-1">
                        <label for="newsletter-email-{{ $source }}" class="sr-only">Email address</label>

                        @if ($isBanner)
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-accent" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                            </svg>
                        @endif

                        <input
                            type="email"
                            name="email"
                            id="newsletter-email-{{ $source }}"
                            value="{{ old('email') }}"
                            required
                            autocomplete="email"
                            placeholder="{{ $isBanner ? 'you@example.com' : 'Email address' }}"
                            @class([
                                'w-full border bg-surface text-ink placeholder:text-ink-faint transition-colors' => true,
                                'rounded-lg px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30' => ! $isBanner,
                                'rounded-full py-3.5 pl-12 pr-5 text-base shadow-[inset_0_2px_6px_rgba(0,0,0,0.18)] hover:border-accent/70 focus:outline-none focus:ring-4 focus:ring-accent/25' => $isBanner,
                                'border-line-strong focus:border-accent' => ! $error,
                                'border-warn focus:ring-warn/30 focus:border-warn' => $error,
                            ])
                        >
                        @if ($error)
                            <p @class(['text-xs text-warn' => true, 'mt-1' => ! $isBanner, 'mt-1.5 pl-5' => $isBanner])>{{ $error }}</p>
                        @endif
                    </div>

                    <x-ui.button
                        type="submit"
                        :variant="$isBanner ? 'contrast' : 'primary'"
                        :size="$isBanner ? 'lg' : 'md'"
                        class="{{ $isBanner ? 'group shrink-0' : 'shrink-0' }}"
                    >
                        {{ $ctaLabel }}
                        @if ($isBanner)
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4 transition-transform duration-150 group-hover:translate-x-1 motion-reduce:transition-none" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                            </svg>
                        @endif
                    </x-ui.button>
                </form>

                <p @class(['mt-3 text-xs text-ink-faint' => true, 'sm:pl-5' => $isBanner])>
                    By subscribing, you agree to receive emails from {{ \App\Models\Setting::get('site_name', 'FynnEdge') }}.
                    You can unsubscribe at any time.
                    @if ($privacyUrl)
                        <a href="{{ $privacyUrl }}" class="underline transition-colors hover:text-ink">Privacy Policy</a>.
                    @endif
                </p>
            </div>
        </div>
    </div>
@endif
