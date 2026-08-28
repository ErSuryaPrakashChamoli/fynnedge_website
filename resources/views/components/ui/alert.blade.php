@props(['tone' => 'accent', 'title' => null])

@php
    $tones = [
        'accent' => ['border-accent/25', 'bg-accent-soft', 'text-accent-strong'],
        'pass' => ['border-pass/25', 'bg-pass-soft', 'text-pass'],
        'warn' => ['border-warn/25', 'bg-warn-soft', 'text-warn'],
    ];
    [$border, $bg, $text] = $tones[$tone] ?? $tones['accent'];
@endphp

<div {{ $attributes->class("flex gap-3 rounded-xl border $border $bg px-4 py-3.5 text-sm") }} role="status">
    <div class="flex-1 {{ $text }}">
        @if ($title)
            <p class="font-semibold">{{ $title }}</p>
        @endif
        <div class="{{ $title ? 'mt-1 text-ink-muted' : '' }}">{{ $slot }}</div>
    </div>
</div>
