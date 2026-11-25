@props(['author'])

{{-- "About the author" after an article's body. $author is an active App\Models\Author or null. --}}
@if ($author)
    @php
        $photo = $author->photoUrl();
    @endphp
    <aside {{ $attributes->class('rounded-2xl border border-line bg-surface p-6 sm:flex sm:items-start sm:gap-5') }} aria-labelledby="about-the-author">
        @if ($photo)
            <img src="{{ $photo }}" alt="{{ $author->name }}" width="80" height="80" loading="lazy" decoding="async" class="h-20 w-20 shrink-0 rounded-full object-cover">
        @else
            <span class="flex h-20 w-20 shrink-0 items-center justify-center rounded-full bg-accent-soft text-xl font-semibold text-accent" aria-hidden="true">{{ $author->initials() }}</span>
        @endif
        <div class="mt-4 min-w-0 sm:mt-0">
            <p id="about-the-author" class="font-mono text-xs uppercase tracking-wide text-ink-faint">{{ __('About the author') }}</p>
            <p class="mt-1 font-display text-lg font-semibold text-ink">{{ $author->name }}</p>
            @if ($author->designation)
                <p class="text-sm text-ink-muted">{{ $author->designation }}</p>
            @endif
            @if ($author->bio)
                <p class="mt-3 text-sm text-ink-muted text-justify hyphens-auto">{{ $author->bio }}</p>
            @endif
            <p class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm font-medium">
                <a href="{{ route('resources.authors.show', $author->slug) }}" class="text-accent hover:underline">{{ __('View all articles by :name', ['name' => $author->name]) }}</a>
                @if ($author->profile_url)
                    <a href="{{ $author->profile_url }}" class="text-accent hover:underline" target="_blank" rel="noopener me">{{ __('Profile') }}</a>
                @endif
            </p>
        </div>
    </aside>
@endif
