<x-layouts.app title="Contact" description="Get in touch with FynnEdge Advisory.">
    <section class="mx-auto max-w-3xl px-6 py-14 lg:px-8">
        <x-ui.breadcrumbs :trail="['Contact' => null]" />

        <h1 class="mt-5 text-balance font-display text-3xl font-semibold tracking-tight text-ink sm:text-4xl">
            Talk to us
        </h1>
        <p class="mt-3 max-w-xl text-ink-muted">
            Questions about a loan product, your eligibility, or an existing application — send us a message
            and we'll get back to you.
        </p>

        @if (session('status'))
            <x-ui.alert tone="pass" class="mt-8">{{ session('status') }}</x-ui.alert>
        @endif

        @if ($contactPhone || $contactEmail || $contactWhatsapp)
            <div class="mt-8 flex flex-wrap gap-4 text-sm text-ink-muted">
                @if ($contactPhone)<span>📞 {{ $contactPhone }}</span>@endif
                @if ($contactEmail)<span>✉️ {{ $contactEmail }}</span>@endif
                @if ($contactWhatsapp)<span>💬 {{ $contactWhatsapp }}</span>@endif
            </div>
        @endif

        <form method="POST" action="{{ route('contact.store') }}" class="mt-10 grid gap-5 sm:grid-cols-2">
            @csrf
            <x-ui.input name="name" label="Name" required class="sm:col-span-1" />
            <x-ui.input name="email" type="email" label="Email" required class="sm:col-span-1" />
            <x-ui.input name="phone" label="Phone (optional)" class="sm:col-span-2" />

            <div class="flex flex-col gap-1.5 sm:col-span-2">
                <label for="message" class="text-sm font-medium text-ink">Message</label>
                <textarea
                    id="message"
                    name="message"
                    rows="5"
                    required
                    class="rounded-lg border px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-faint transition-colors focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/30 {{ $errors->has('message') ? 'border-warn' : 'border-line-strong' }}"
                >{{ old('message') }}</textarea>
                @error('message')<p class="text-xs text-warn">{{ $message }}</p>@enderror
            </div>

            <div class="sm:col-span-2">
                <x-ui.button type="submit">Send message</x-ui.button>
            </div>
        </form>
    </section>
</x-layouts.app>
