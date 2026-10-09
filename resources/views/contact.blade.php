<x-layouts.app title="Contact" description="Get in touch with FynnEdge Advisory." page-type="ContactPage">
    <section class="mx-auto max-w-7xl px-6 py-14 lg:px-8">
        <x-ui.breadcrumbs :trail="['Contact' => null]" />

        <h1 data-reveal="up" class="mt-5 text-balance font-display text-3xl font-semibold tracking-tight text-ink sm:text-4xl">
            Talk to us
        </h1>
        <p data-reveal="up" class="mt-3 text-ink-muted text-justify hyphens-auto">
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
            <x-site.location-map
                data-reveal="zoom"
                class="mt-12 h-80 rounded-2xl border border-line sm:h-96"
                :src="$contactMapUrl"
                :view-url="$contactMapViewUrl"
                label="FynnEdge Advisory"
            />
        @endif
    </section>
</x-layouts.app>
