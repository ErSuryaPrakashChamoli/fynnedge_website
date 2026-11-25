@props(['author', 'size' => 'md'])

{{--
    "Written by" line for an article: the author's photo (or initials), name
    linking to their author page, and designation. $author is an
    App\Models\Author (only an active one — Article::publicAuthor()); renders
    nothing without one. The photo is small and lazy, so it never competes with
    the cover image for the page's Largest Contentful Paint.
--}}
@if ($author)
    @php
        $photo = $author->photoUrl();
        $dimension = $size === 'sm' ? 'h-7 w-7 text-[10px]' : 'h-10 w-10 text-xs';
    @endphp
    <div {{ $attributes->class('flex items-center gap-3') }}>
        @if ($photo)
            <img src="{{ $photo }}" alt="{{ $author->name }}" width="40" height="40" loading="lazy" decoding="async" class="{{ $dimension }} shrink-0 rounded-full object-cover ring-2 ring-surface">
        @else
            <span class="{{ $dimension }} flex shrink-0 items-center justify-center rounded-full bg-accent-soft font-semibold text-accent" aria-hidden="true">{{ $author->initials() }}</span>
        @endif
        <p class="min-w-0 text-sm leading-tight">
            <span class="text-ink-faint">{{ __('Written by') }}</span>
            <a href="{{ route('resources.authors.show', $author->slug) }}" class="font-semibold text-ink hover:text-accent" rel="author">{{ $author->name }}</a>
            @if ($author->designation && $size !== 'sm')
                <span class="block text-xs text-ink-muted">{{ $author->designation }}</span>
            @endif
        </p>
    </div>
@endif
