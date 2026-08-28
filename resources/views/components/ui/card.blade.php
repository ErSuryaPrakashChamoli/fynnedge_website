@props(['padded' => true])

<div {{ $attributes->class([
    'rounded-2xl border border-line bg-surface shadow-sm shadow-ink/[0.03]',
    'p-6 sm:p-7' => $padded,
]) }}>
    {{ $slot }}
</div>
