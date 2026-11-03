@props(['lender', 'size' => 'md'])

@php
    $sizes = [
        'sm' => 'h-8 w-8 text-xs',
        'md' => 'h-11 w-11 text-sm',
        'lg' => 'h-14 w-14 text-base',
    ];
    $sizeClass = $sizes[$size] ?? $sizes['md'];
    $pixels = ['sm' => 32, 'md' => 44, 'lg' => 56][$size] ?? 44;
    $srcset = $lender->logoUrl() ? $lender->imageSrcset() : null;
    $initials = collect(preg_split('/\s+/', trim($lender->name)))
        ->map(fn ($word) => mb_substr($word, 0, 1))
        ->take(2)
        ->implode('');
@endphp

@if ($lender->logoUrl())
    {{-- display: contents keeps the <img> itself the flex item its callers lay out. --}}
    <picture class="contents">
        @if ($srcset)
            <source type="image/webp" srcset="{{ $srcset }}" sizes="{{ $pixels }}px">
        @endif
        <img
            src="{{ $lender->logoUrl() }}"
            alt="{{ __(':lender logo', ['lender' => $lender->name]) }}"
            loading="lazy"
            {{ $attributes->class('shrink-0 rounded-full border border-line bg-white object-contain p-1.5 '.$sizeClass) }}
        >
    </picture>
@else
    <span
        {{ $attributes->class('flex shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono font-semibold uppercase text-accent '.$sizeClass) }}
        aria-hidden="true"
    >{{ $initials }}</span>
@endif
