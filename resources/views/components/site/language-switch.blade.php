{{--
    English/Hindi toggle. Each option is a plain link to locale.switch, which
    stores the choice and returns the visitor to this same page. Each name is
    written in its own language and script, so a visitor can find theirs
    whichever language the page is in right now.
--}}
@php
    $currentLocale = \App\Support\Localization\SiteLocale::current();
    $returnPath = request()->getRequestUri();
@endphp

<nav {{ $attributes->class('inline-flex shrink-0 items-center rounded-full border border-line-strong bg-surface p-0.5') }} aria-label="{{ __('Choose language') }}">
    @foreach (\App\Support\Localization\SiteLocale::SUPPORTED as $code => $name)
        <a
            href="{{ route('locale.switch', ['locale' => $code, 'return' => $returnPath]) }}"
            lang="{{ $code }}"
            hreflang="{{ $code }}"
            rel="nofollow"
            data-locale-option="{{ $code }}"
            @if ($code === $currentLocale) aria-current="true" @endif
            @class([
                'rounded-full px-3 py-0.5 text-xs font-semibold leading-5 transition-colors',
                'bg-accent text-white' => $code === $currentLocale,
                'text-ink-muted hover:bg-surface-2 hover:text-ink' => $code !== $currentLocale,
            ])
        >{{ $name }}</a>
    @endforeach
</nav>
