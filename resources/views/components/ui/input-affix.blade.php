{{--
    The leading box inside a bordered input group (the "+91" on a phone field,
    the "₹" on an amount field, an icon on name/email). Every affix is the same
    fixed width so that, stacked in a form, the text in each field starts at the
    same x — that alignment is the whole point, so don't swap w-11 for padding.
--}}
<span {{ $attributes->class('flex w-11 shrink-0 select-none items-center justify-center border-r border-line text-sm text-ink-muted') }}>
    {{ $slot }}
</span>
