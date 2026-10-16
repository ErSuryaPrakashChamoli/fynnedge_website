@props(['title', 'body' => null])

<div {{ $attributes->class('flex flex-col items-start gap-3 rounded-xl border border-dashed border-line-strong bg-surface-2 p-5') }}>
    <p class="font-medium text-ink">{{ $title }}</p>
    @if ($body)
        <p class="text-sm text-ink-muted">{{ $body }}</p>
    @endif
    {{ $slot }}
</div>
