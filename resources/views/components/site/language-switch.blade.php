{{--
    English/Hindi toggle. Each option is a small POST form to locale.switch,
    which stores the choice and returns the visitor to this same page. Each
    name is written in its own language and script, so a visitor can find
    theirs whichever language the page is in right now.

    Forms, not links, on purpose: an <a href> here put two crawlable
    /language/{locale}?return=<this page> URLs on every page of the site, and
    Google crawled hundreds of them (rel="nofollow" is only a hint) and listed
    each as "Page with redirect" in Search Console. Crawlers do not submit POST
    forms, and the form still works without JavaScript. The route is exempt
    from CSRF (bootstrap/app.php), so a page left open for hours can never
    answer the switch with an expired-token error.
--}}
@php
    $currentLocale = \App\Support\Localization\SiteLocale::current();
    $returnPath = request()->getRequestUri();
@endphp

<nav {{ $attributes->class('inline-flex shrink-0 items-center rounded-full border border-line-strong bg-surface p-0.5') }} aria-label="{{ __('Choose language') }}">
    @foreach (\App\Support\Localization\SiteLocale::SUPPORTED as $code => $name)
        <form method="POST" action="{{ route('locale.switch', ['locale' => $code]) }}" class="contents">
            <input type="hidden" name="return" value="{{ $returnPath }}">
            <button
                type="submit"
                lang="{{ $code }}"
                data-locale-option="{{ $code }}"
                @if ($code === $currentLocale) aria-current="true" @endif
                @class([
                    'cursor-pointer rounded-full px-3 py-0.5 text-xs font-semibold leading-5 transition-colors',
                    'bg-accent text-white' => $code === $currentLocale,
                    'text-ink-muted hover:bg-surface-2 hover:text-ink' => $code !== $currentLocale,
                ])
            >{{ $name }}</button>
        </form>
    @endforeach
</nav>
