@php
    $shouldPrompt = \App\Support\Privacy\CookieConsent::shouldPrompt();
    $policyLinks = \App\Support\Privacy\CookieConsent::policyLinks();
    $analyticsRequired = \App\Support\Privacy\CookieConsent::analyticsRequired();
    $marketingRequired = \App\Support\Privacy\CookieConsent::marketingRequired();
@endphp

@if ($shouldPrompt)
    {{--
        The banner writes a first-party cookie listing the accepted categories
        and reloads, because the tags it gates are rendered SERVER-side (see
        App\Support\Analytics\TrackingScripts): blocking them in the browser
        after the fact would already have loaded the third-party JS, which is
        the thing consent is meant to prevent. Reloading once, on the visitor's
        own click, is the honest version of that trade-off.

        Built from the same design tokens as the rest of the site, so it
        inherits both themes and any admin appearance overrides.
    --}}
    <div
        x-data="{
            open: true,
            save(categories) {
                document.cookie = '{{ \App\Support\Privacy\CookieConsent::COOKIE }}=' + categories
                    + ';path=/;max-age=' + (60 * 60 * 24 * {{ \App\Support\Privacy\CookieConsent::LIFETIME_DAYS }})
                    + ';SameSite=Lax' + (location.protocol === 'https:' ? ';Secure' : '');
                this.open = false;
                window.location.reload();
            },
        }"
        x-show="open"
        x-cloak
        role="dialog"
        aria-live="polite"
        aria-label="Cookie preferences"
        class="fixed inset-x-0 bottom-0 z-50 border-t border-line bg-surface/95 p-4 backdrop-blur sm:p-6"
    >
        <div class="mx-auto flex max-w-5xl flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="text-sm text-ink-muted">
                <p>
                    We use essential cookies to run this site
                    @if ($analyticsRequired && $marketingRequired)
                        and, with your permission, analytics and marketing cookies to understand usage and measure our campaigns.
                    @elseif ($analyticsRequired)
                        and, with your permission, analytics cookies to understand how it is used.
                    @elseif ($marketingRequired)
                        and, with your permission, marketing cookies to measure our campaigns.
                    @else
                        .
                    @endif
                </p>
                @if ($policyLinks !== [])
                    <p class="mt-2 flex flex-wrap gap-x-4 gap-y-1 font-mono text-xs">
                        @foreach ($policyLinks as $label => $url)
                            <a href="{{ $url }}" class="underline hover:text-ink">{{ $label }}</a>
                        @endforeach
                    </p>
                @endif
            </div>

            <div class="flex shrink-0 flex-wrap gap-3">
                <button
                    type="button"
                    x-on:click="save('none')"
                    class="rounded-lg border border-line px-4 py-2 text-sm font-medium text-ink transition hover:bg-bg"
                >
                    Essential only
                </button>
                <button
                    type="button"
                    x-on:click="save('analytics,marketing')"
                    class="rounded-lg bg-accent px-4 py-2 text-sm font-medium text-white transition hover:opacity-90"
                >
                    Accept all
                </button>
            </div>
        </div>
    </div>
@endif
