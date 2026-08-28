@props(['route', 'label'])

@if (Route::has($route))
    <a href="{{ route($route) }}" {{ $attributes->class('text-sm font-medium text-ink-muted hover:text-ink transition-colors') }}>
        {{ $label }}
    </a>
@else
    <span {{ $attributes->class('text-sm font-medium text-ink-faint/70 cursor-default select-none') }} title="Coming soon">
        {{ $label }}
    </span>
@endif
