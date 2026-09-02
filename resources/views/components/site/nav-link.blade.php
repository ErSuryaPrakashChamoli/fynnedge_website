@props(['route', 'label', 'parameters' => []])

@if (Route::has($route))
    @php
        $isActive = request()->routeIs($route)
            && collect($parameters)->every(function ($value, $key) {
                $routeValue = request()->route($key);
                $routeValue = $routeValue instanceof \BackedEnum ? $routeValue->value : $routeValue;

                return (string) $routeValue === (string) $value;
            });
    @endphp
    <a
        href="{{ route($route, $parameters) }}"
        @if ($isActive) aria-current="page" @endif
        {{ $attributes->class([
            'text-sm font-medium transition-colors',
            'text-accent font-semibold' => $isActive,
            'text-ink-muted hover:text-ink' => ! $isActive,
        ]) }}
    >
        {{ $label }}
    </a>
@else
    <span {{ $attributes->class('text-sm font-medium text-ink-faint/70 cursor-default select-none') }} title="Coming soon">
        {{ $label }}
    </span>
@endif
