@props(['code', 'heading', 'message'])

{{--
    The body of the 404 and 410 pages: what happened, then the main sections of
    the site. Links only, built from named routes, so the page itself can never
    hit a missing record or a slow query, and never redirects anywhere.
--}}
<section class="mx-auto max-w-7xl px-6 py-14 lg:px-8">
    <p class="text-sm font-semibold uppercase tracking-wider text-accent">{{ __('Error :code', ['code' => $code]) }}</p>

    <h1 class="mt-3 text-balance font-display text-3xl font-semibold tracking-tight text-ink sm:text-4xl">
        {{ $heading }}
    </h1>
    <p class="mt-3 text-lg text-ink-muted">{{ $message }}</p>

    <div class="mt-6 flex flex-wrap gap-3">
        <x-ui.button tag="a" href="{{ route('home') }}">{{ __('Go to the homepage') }}</x-ui.button>
        <x-ui.button tag="a" variant="secondary" href="{{ route('contact') }}">{{ __('Contact us') }}</x-ui.button>
    </div>

    <h2 class="mt-12 font-display text-xl font-semibold tracking-tight text-ink">{{ __('You may be looking for') }}</h2>

    <ul class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['url' => route('loans.index'), 'title' => __('Loans'), 'text' => __('Personal, home, business and other loans from banks and NBFCs.')],
            ['url' => route('calculators.index'), 'title' => __('Calculators'), 'text' => __('EMI, eligibility, prepayment and investment calculators.')],
            ['url' => route('resources.index'), 'title' => __('Resources'), 'text' => __('Guides and news on loans, credit scores and money.')],
            ['url' => route('contact'), 'title' => __('Talk to us'), 'text' => __('Tell us what you were looking for and we will help.')],
        ] as $link)
            <li>
                <a href="{{ $link['url'] }}" class="block h-full rounded-2xl border border-line bg-surface p-5 shadow-sm shadow-ink/[0.03] transition hover:border-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">
                    <span class="font-medium text-ink">{{ $link['title'] }}</span>
                    <span class="mt-1 block text-sm text-ink-muted">{{ $link['text'] }}</span>
                </a>
            </li>
        @endforeach
    </ul>
</section>
