@props(['trail' => []])

<nav aria-label="Breadcrumb" {{ $attributes }}>
    <ol class="flex flex-wrap items-center gap-2 font-mono text-xs text-ink-faint">
        <li><a href="{{ route('home') }}" class="hover:text-ink">Home</a></li>
        @foreach ($trail as $label => $url)
            <li aria-hidden="true">/</li>
            <li>
                @if ($url && ! $loop->last)
                    <a href="{{ $url }}" class="hover:text-ink">{{ $label }}</a>
                @else
                    <span class="text-ink" aria-current="page">{{ $label }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
