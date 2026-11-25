@php
    $photo = $author->photoUrl();
    $description = $author->bio ? \Illuminate\Support\Str::limit($author->bio, 155) : __('Articles by :name', ['name' => $author->name]);
@endphp
<x-layouts.app :title="__('Articles by :name', ['name' => $author->name])" :description="$description" og-type="profile">
    <section class="mx-auto max-w-7xl px-6 py-14 lg:px-8">
        <x-ui.breadcrumbs :trail="[__('Resources') => route('resources.index'), $author->name => null]" />

        <div class="mt-6 flex flex-col gap-6 sm:flex-row sm:items-center">
            @if ($photo)
                <img src="{{ $photo }}" alt="{{ $author->name }}" width="112" height="112" class="h-28 w-28 shrink-0 rounded-full object-cover">
            @else
                <span class="flex h-28 w-28 shrink-0 items-center justify-center rounded-full bg-accent-soft text-3xl font-semibold text-accent" aria-hidden="true">{{ $author->initials() }}</span>
            @endif
            <div class="min-w-0">
                <p class="font-mono text-xs uppercase tracking-wide text-ink-faint">{{ __('Author') }}</p>
                <h1 class="mt-1 text-balance font-display text-3xl font-semibold tracking-tight text-ink sm:text-4xl">{{ $author->name }}</h1>
                @if ($author->designation)
                    <p class="mt-1 text-ink-muted">{{ $author->designation }}</p>
                @endif
                @if ($author->profile_url)
                    <a href="{{ $author->profile_url }}" class="mt-2 inline-block text-sm font-medium text-accent hover:underline" target="_blank" rel="noopener me">{{ __('Profile') }}</a>
                @endif
            </div>
        </div>

        @if ($author->bio)
            <p class="mt-6 text-ink-muted text-justify hyphens-auto">{{ $author->bio }}</p>
        @endif

        <h2 class="mt-12 font-display text-xl font-semibold text-ink">{{ __('Articles by :name', ['name' => $author->name]) }}</h2>

        @if ($articles->isEmpty())
            <x-ui.alert tone="accent" class="mt-6">{{ __('No published articles yet.') }}</x-ui.alert>
        @else
            <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($articles as $article)
                    <x-site.article-card :article="$article" />
                @endforeach
            </div>
        @endif

        <script type="application/ld+json">
            {!! json_encode(array_filter([
                '@@context' => 'https://schema.org',
                '@type' => 'ProfilePage',
                'mainEntity' => array_filter([
                    '@type' => 'Person',
                    'name' => $author->name,
                    'jobTitle' => $author->designation,
                    'description' => $author->bio,
                    'image' => $photo,
                    'sameAs' => $author->profile_url ? [$author->profile_url] : null,
                    'worksFor' => ['@id' => \App\Support\Seo\SchemaGraph::organizationId()],
                ]),
            ]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}
        </script>
    </section>
</x-layouts.app>
