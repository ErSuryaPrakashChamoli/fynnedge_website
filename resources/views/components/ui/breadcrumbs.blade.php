@props(['trail' => []])

{{--
    BreadcrumbList JSON-LD is derived from the exact same $trail already
    rendered visually below — never a separate, hand-maintained copy that
    could drift from what the visitor actually sees. The last crumb (the
    current page) never has a URL visually, but schema.org's BreadcrumbList
    still expects one per item, so it uses the current request URL.
--}}
@php
    $breadcrumbList = collect([['label' => 'Home', 'url' => route('home')]])
        ->merge(collect($trail)->map(fn ($url, $label) => ['label' => $label, 'url' => $url])->values())
        ->values()
        ->map(fn (array $crumb, int $index) => [
            '@type' => 'ListItem',
            'position' => $index + 1,
            'name' => $crumb['label'],
            'item' => $crumb['url'] ?: url()->current(),
        ]);
@endphp

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

@if (count($trail) > 0)
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $breadcrumbList->all(),
        ]) !!}
    </script>
@endif
