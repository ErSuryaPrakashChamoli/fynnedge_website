<x-layouts.app title="Contact" description="Get in touch with FynnEdge Advisory." page-type="ContactPage">
    <section class="mx-auto max-w-3xl px-6 py-14 lg:px-8">
        <x-ui.breadcrumbs :trail="['Contact' => null]" />

        <h1 data-reveal="up" class="mt-5 text-balance font-display text-3xl font-semibold tracking-tight text-ink sm:text-4xl">
            Talk to us
        </h1>
        <p data-reveal="up" class="mt-3 max-w-xl text-ink-muted">
            Questions about a loan product, your eligibility, or an existing application — send us a message
            and we'll get back to you.
        </p>

        @if (session('status'))
            <x-ui.alert tone="pass" :title="session('statusTitle')" class="mt-8">{{ session('status') }}</x-ui.alert>
        @endif

        @if ($contactPhone || $contactEmail || $contactWhatsapp || $contactAddress)
            <div class="mt-8 flex flex-wrap gap-4 text-sm text-ink-muted">
                @if ($contactPhone)<span>📞 {{ $contactPhone }}</span>@endif
                @if ($contactEmail)<span>✉️ {{ $contactEmail }}</span>@endif
                @if ($contactWhatsapp)<span>💬 {{ $contactWhatsapp }}</span>@endif
                @if ($contactAddress)<span>📍 {{ $contactAddress }}</span>@endif
            </div>
        @endif

        <form method="POST" action="{{ route('contact.store') }}" data-reveal="up" class="mt-10 grid gap-5 sm:grid-cols-2">
            @csrf
            <x-ui.input name="name" label="Name" required class="sm:col-span-1" />
            <x-ui.input name="email" type="email" label="Email" required class="sm:col-span-1" />
            <x-ui.input name="phone" label="Phone (optional)" class="sm:col-span-2" />

            <x-ui.textarea name="message" label="Message" rows="5" required class="sm:col-span-2" :value="$prefillMessage" />

            <div class="sm:col-span-2">
                <x-ui.button type="submit">Send message</x-ui.button>
            </div>
        </form>

        @if ($contactMapUrl)
            <div data-reveal="zoom" class="relative mt-12 overflow-hidden rounded-2xl border border-line">
                <iframe
                    src="{{ $contactMapUrl }}"
                    class="h-80 w-full sm:h-96"
                    style="border: 0"
                    allowfullscreen
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                    title="Our location"
                ></iframe>

                {{-- The map link centers on our office coordinates, so the red pin always
                     lands at the iframe's exact center — this label floats just above it. --}}
                <div
                    class="pointer-events-none absolute left-1/2 top-1/2 -translate-x-1/2"
                    style="transform: translate(-50%, calc(-100% - 34px))"
                >
                    <span class="whitespace-nowrap rounded-full border border-line bg-surface px-3 py-1 text-xs font-semibold text-ink shadow-md">
                        FynnEdge Advisory
                    </span>
                </div>

                @if ($contactMapViewUrl)
                    {{-- Clicking the pin itself tries to load Google Place details, which fails
                         with "Place info couldn't load" since there's no listing at this address.
                         This sits on top of just the pin's icon and opens real Google Maps instead. --}}
                    <a
                        href="{{ $contactMapViewUrl }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="absolute left-1/2 top-1/2 h-11 w-9 -translate-x-1/2 -translate-y-full cursor-pointer"
                        aria-label="Open FynnEdge Advisory location in Google Maps"
                    ></a>
                @endif
            </div>
        @endif
    </section>
</x-layouts.app>
