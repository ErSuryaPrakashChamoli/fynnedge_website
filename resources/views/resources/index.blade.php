<x-layouts.app :title="__('Resources')" :description="__('Guides and articles to help you understand loans, eligibility and the application process.')">
    <section class="mx-auto max-w-7xl px-6 py-14 lg:px-8">
        <x-ui.breadcrumbs :trail="[__('Resources') => null]" />

        <h1 data-reveal="up" class="mt-5 max-w-2xl text-balance font-display text-3xl font-semibold tracking-tight text-ink sm:text-4xl">
            {{ __('Guides & resources') }}
        </h1>
        <p data-reveal="up" class="mt-3 text-ink-muted text-justify hyphens-auto">
            {{ __('Plain-language guides on loans, eligibility and the application process.') }}
        </p>

        @if ($articles->isEmpty())
            <x-ui.alert tone="accent" class="mt-10">
                {{ __('Guides are being added. Check back shortly.') }}
            </x-ui.alert>
        @else
            <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($articles as $article)
                    <x-site.article-card :article="$article" />
                @endforeach
            </div>
        @endif

        <x-site.newsletter-form
            class="mt-14"
            variant="compact"
            source="blog_index"
            :heading="__('Never miss an article')"
            :description="__('New guides on credit, loans and everyday money, sent as they are published.')"
        />
    </section>
</x-layouts.app>
