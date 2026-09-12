@props(['id', 'field', 'server' => null])

{{--
    One inline validation message per field, in both directions: the
    server-rendered one for the no-JavaScript path (hidden again as soon as
    Alpine has its own message for that field), and Alpine's own for the AJAX
    path. Both carry role="alert" so a screen reader announces them.
--}}
@if ($server)
    <p class="mt-1 text-xs text-warn" role="alert" x-show="!errors.{{ $field }}">{{ $server }}</p>
@endif

<p
    id="{{ $id }}-error"
    role="alert"
    class="mt-1 text-xs text-warn"
    x-show="errors.{{ $field }}"
    x-text="errors.{{ $field }}"
    x-cloak
></p>
