@props(['article'])

<a href="{{ route('resources.show', $article) }}" data-reveal="up stagger" class="group">
    <x-ui.card class="card-lift h-full transition-colors transition-shadow group-hover:bg-accent-soft group-hover:shadow-md group-hover:animate-card-swing">
        {{-- sizes: the image box inside a card in the 1/2/3-column grids on the homepage and /resources. --}}
        @if ($article->imageUrl())
            <picture class="block">
                @if ($imageSrcset = $article->imageSrcset())
                    <source type="image/webp" srcset="{{ $imageSrcset }}" sizes="(min-width: 1280px) 332px, (min-width: 1024px) calc(33vw - 95px), (min-width: 640px) calc(50vw - 94px), calc(100vw - 98px)">
                @endif
                <img
                    src="{{ $article->imageUrl() }}"
                    alt="{{ $article->image_alt ?? '' }}"
                    loading="lazy"
                    class="mb-5 aspect-video w-full rounded-xl object-cover"
                >
            </picture>
        @endif
        <p class="font-mono text-xs text-ink-faint">{{ $article->publishedOn()->translatedFormat('d M Y') }}</p>
        <p class="mt-3 font-display text-xl font-semibold text-ink group-hover:text-accent">{{ $article->title }}</p>
        @if ($article->excerpt)
            <p class="mt-2 text-sm text-ink-muted">{{ $article->excerpt }}</p>
        @endif
        <span class="mt-4 inline-flex items-center gap-1 text-sm font-medium text-accent">
            {{ __('Read more') }}
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" class="h-3.5 w-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8h10m0 0-4-4m4 4-4 4" /></svg>
        </span>
    </x-ui.card>
</a>
