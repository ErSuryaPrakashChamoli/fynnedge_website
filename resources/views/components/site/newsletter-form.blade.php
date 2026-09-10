@props([
    'source' => 'website',
    'sourceUrl' => null,
    'variant' => 'card',
    'heading' => 'Stay ahead of your finances',
    'description' => 'Get practical financial insights, loan tips and useful updates from FynnEdge directly in your inbox.',
    'ctaLabel' => 'Subscribe',
    'showName' => false,
])

@php
    /*
        One component, three placements. It renders with the site's existing
        tokens and x-ui.* components only — no newsletter-specific colours,
        fonts or button styles — so it inherits the theme (including the
        per-section appearance overrides from Settings) like everything else.

        Nothing renders at all when the newsletter is switched off in the admin
        panel, so disabling it removes the forms rather than leaving dead ones.
    */
    $enabled = \App\Modules\Newsletter\Services\NewsletterSettings::enabled();
    $status = session('newsletterStatus');
    $error = $errors->first('email');
    $privacyUrl = \Illuminate\Support\Facades\Route::has('privacy-policy') ? route('privacy-policy') : null;
    $resolvedSourceUrl = $sourceUrl ?? request()->path();
@endphp

@if ($enabled)
    <div id="newsletter" {{ $attributes->class([
        'rounded-2xl border border-line bg-surface p-6 sm:p-8' => $variant === 'card',
        'rounded-xl border border-line bg-surface-2 p-5' => $variant === 'compact',
        '' => $variant === 'inline',
    ]) }}>
        @if ($heading)
            <p @class([
                'font-display font-semibold text-ink' => true,
                'text-xl sm:text-2xl' => $variant === 'card',
                'text-base' => $variant !== 'card',
            ])>{{ $heading }}</p>
        @endif

        @if ($description)
            <p @class([
                'mt-2 text-ink-muted' => true,
                'text-sm sm:text-base' => $variant === 'card',
                'text-sm' => $variant !== 'card',
            ])>{{ $description }}</p>
        @endif

        @if ($status)
            <x-ui.alert class="mt-4" tone="pass">{{ $status }}</x-ui.alert>
        @endif

        <form method="POST" action="{{ route('newsletter.subscribe') }}" class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-start">
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

            <div class="flex-1">
                <label for="newsletter-email-{{ $source }}" class="sr-only">Email address</label>
                <input
                    type="email"
                    name="email"
                    id="newsletter-email-{{ $source }}"
                    value="{{ old('email') }}"
                    required
                    autocomplete="email"
                    placeholder="Email address"
                    @class([
                        'w-full rounded-lg border bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-faint transition-colors',
                        'focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent',
                        'border-line-strong' => ! $error,
                        'border-warn focus:ring-warn/30 focus:border-warn' => $error,
                    ])
                >
                @if ($error)
                    <p class="mt-1 text-xs text-warn">{{ $error }}</p>
                @endif
            </div>

            <x-ui.button type="submit" class="shrink-0">{{ $ctaLabel }}</x-ui.button>
        </form>

        <p class="mt-3 text-xs text-ink-faint">
            By subscribing, you agree to receive emails from {{ \App\Models\Setting::get('site_name', 'FynnEdge') }}.
            You can unsubscribe at any time.
            @if ($privacyUrl)
                <a href="{{ $privacyUrl }}" class="underline transition-colors hover:text-ink">Privacy Policy</a>.
            @endif
        </p>
    </div>
@endif
