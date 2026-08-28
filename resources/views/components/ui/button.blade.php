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
    ];

    $sizes = [
        'sm' => 'px-3.5 py-1.5 text-sm',
        'md' => 'px-5 py-2.5 text-sm',
        'lg' => 'px-6 py-3.5 text-base',
    ];

    $classes = 'inline-flex items-center justify-center gap-2 rounded-full font-medium tracking-tight transition-colors duration-150 focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-50 disabled:pointer-events-none '
        .($variants[$variant] ?? $variants['primary']).' '
        .($sizes[$size] ?? $sizes['md']);
@endphp

<{{ $tag }} {{ $attributes->class($classes) }}>{{ $slot }}</{{ $tag }}>
