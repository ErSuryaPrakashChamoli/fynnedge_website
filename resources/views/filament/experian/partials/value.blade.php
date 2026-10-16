{{--
    Renders any value from an Experian response — scalar, list or nested object —
    so fields with no dedicated layout are still shown rather than dropped.
    Very long strings (e.g. an embedded printable report) are cut short here;
    the complete value is in the raw JSON view.
--}}
@php
    $depth = $depth ?? 0;
@endphp

@if (is_array($value) && $value === [])
    <span class="text-gray-400">—</span>
@elseif (is_array($value) && $depth >= 6)
    <span class="text-gray-500 dark:text-gray-400">Nested further — see raw JSON.</span>
@elseif (is_array($value) && array_is_list($value))
    <ol class="list-decimal space-y-2 ps-5">
        @foreach ($value as $item)
            <li>@include('filament.experian.partials.value', ['value' => $item, 'depth' => $depth + 1])</li>
        @endforeach
    </ol>
@elseif (is_array($value))
    <dl class="grid gap-x-4 gap-y-1 sm:grid-cols-[minmax(10rem,auto)_1fr]">
        @foreach ($value as $key => $item)
            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $key }}</dt>
            <dd class="min-w-0 break-words text-sm text-gray-950 dark:text-white">
                @include('filament.experian.partials.value', ['value' => $item, 'depth' => $depth + 1])
            </dd>
        @endforeach
    </dl>
@elseif ($value === null || $value === '')
    <span class="text-gray-400">—</span>
@elseif (is_bool($value))
    {{ $value ? 'Yes' : 'No' }}
@elseif (mb_strlen((string) $value) > 500)
    <span>{{ mb_substr((string) $value, 0, 200) }}…</span>
    <span class="text-xs text-gray-500 dark:text-gray-400">({{ number_format(mb_strlen((string) $value)) }} characters — see raw JSON)</span>
@else
    {{ $value }}
@endif
