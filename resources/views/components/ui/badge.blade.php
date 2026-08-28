@props(['tone' => 'accent'])

@php
    $tones = [
        'accent' => 'bg-accent-soft text-accent',
        'pass' => 'bg-pass-soft text-pass',
        'warn' => 'bg-warn-soft text-warn',
        'muted' => 'bg-surface-2 text-ink-faint',
    ];
@endphp

<span {{ $attributes->class(
    'inline-flex items-center gap-1.5 rounded-full px-3 py-1 font-mono text-[0.68rem] font-semibold uppercase tracking-wider '
        .($tones[$tone] ?? $tones['accent']),
) }}>
    {{ $slot }}
</span>
