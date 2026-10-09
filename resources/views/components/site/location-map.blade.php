@props([
    'src',
    'label',
    'viewUrl' => null,
    'title' => 'Our location',
])

{{--
    The admin-set Google Maps embed (Settings → Contact channels), used by the
    contact page and the footer's office map card. Size and frame it from the
    call site: the iframe fills whatever height the wrapper is given.
--}}
<div {{ $attributes->class('relative overflow-hidden') }}>
    <iframe
        src="{{ $src }}"
        class="block h-full w-full"
        style="border: 0"
        allowfullscreen
        loading="lazy"
        referrerpolicy="no-referrer-when-downgrade"
        title="{{ $title }}"
    ></iframe>

    {{-- $viewUrl is only set for a bare coordinate pin (OfficeMap::viewUrl()). A map
         of a Google listing names its own pin and shows a place card, so neither
         overlay is drawn over it. --}}
    @if ($viewUrl)
        {{-- The map link centers on our office coordinates, so the red pin always
             lands at the iframe's exact center — this label floats just above it.
             Positioned by the inline transform alone: Tailwind v4's -translate-x-*
             sets the separate `translate` property, which stacks with `transform`
             and pushed the label a full width left of the pin. --}}
        <div
            class="pointer-events-none absolute left-1/2 top-1/2"
            style="transform: translate(-50%, calc(-100% - 44px))"
        >
            <span class="whitespace-nowrap rounded-full border border-line bg-surface px-3 py-1 text-xs font-semibold text-ink shadow-md">
                {{ $label }}
            </span>
        </div>

        {{-- Clicking the pin itself tries to load Google Place details, which fails
             with "Place info couldn't load" since there's no listing at this address.
             This sits on top of just the pin's icon and opens real Google Maps instead. --}}
        <a
            href="{{ $viewUrl }}"
            target="_blank"
            rel="noopener noreferrer"
            class="absolute left-1/2 top-1/2 h-11 w-9 -translate-x-1/2 -translate-y-full cursor-pointer"
            aria-label="Open {{ $label }} location in Google Maps"
        ></a>
    @endif
</div>
