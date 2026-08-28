@props(['label' => null, 'name', 'hint' => null, 'rows' => 4])

@php $errorBag = $errors->has($name); @endphp

<div class="flex flex-col gap-1.5">
    @if ($label)
        <label for="{{ $name }}" class="text-sm font-medium text-ink">{{ $label }}</label>
    @endif

    <textarea
        name="{{ $name }}"
        id="{{ $name }}"
        rows="{{ $rows }}"
        {{ $attributes->class([
            'rounded-lg border bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-faint transition-colors',
            'focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent',
            'border-line-strong' => ! $errorBag,
            'border-warn focus:ring-warn/30 focus:border-warn' => $errorBag,
        ]) }}
    >{{ old($name, $attributes->get('value')) }}</textarea>

    @if ($errorBag)
        <p class="text-xs text-warn">{{ $errors->first($name) }}</p>
    @elseif ($hint)
        <p class="text-xs text-ink-faint">{{ $hint }}</p>
    @endif
</div>
