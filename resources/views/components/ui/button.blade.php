@props([
    'variant' => 'primary',
    'size' => 'md',
    'tag' => 'button',
])

@php
    $variants = [
        'primary' => 'bg-accent text-white hover:bg-accent-strong focus-visible:outline-accent',
        'secondary' => 'bg-transparent text-accent border border-line-strong hover:bg-surface-2 focus-visible:outline-accent',
        'ghost' => 'bg-transparent text-ink-muted hover:text-ink hover:bg-surface-2 focus-visible:outline-accent',
        'inverse' => 'bg-white text-accent hover:bg-white/90 focus-visible:outline-white',
        // Reads as the loudest thing on a panel in any theme: ink and surface always contrast.
        'contrast' => 'bg-ink text-surface shadow-sm hover:bg-ink/90 hover:-translate-y-0.5 hover:shadow-lg active:translate-y-0 active:shadow-sm focus-visible:outline-ink',
        // Quiet at rest, fills with the accent on hover: for a row of equal-weight choices.
        'outline' => 'bg-transparent text-accent border border-line-strong hover:border-accent hover:bg-accent hover:text-white hover:-translate-y-0.5 hover:shadow-md active:translate-y-0 active:shadow-sm focus-visible:outline-accent',
    ];

    $sizes = [
        'sm' => 'px-3.5 py-1.5 text-sm',
        'md' => 'px-5 py-2.5 text-sm',
        'lg' => 'px-6 py-3.5 text-base',
        /*
            Sized by the nearest `@container`, not the viewport — for a row of
            buttons that must share one line in a column of varying width. It
            steps up to md, then lg padding as the container allows; below that
            a label wraps inside its own button rather than the row wrapping.
        */
        'fit' => 'px-3 py-3 text-center text-[0.8125rem] leading-tight @lg:px-4 @lg:text-sm @min-[38rem]:px-6 @min-[38rem]:py-3.5 @min-[38rem]:text-base',
    ];

    $classes = 'inline-flex items-center justify-center gap-2 rounded-full font-medium tracking-tight transition duration-150 motion-reduce:transition-none motion-reduce:hover:translate-y-0 focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-50 disabled:pointer-events-none '
        .($variants[$variant] ?? $variants['primary']).' '
        .($sizes[$size] ?? $sizes['md']);
@endphp

<{{ $tag }} {{ $attributes->class($classes) }}>{{ $slot }}</{{ $tag }}>
