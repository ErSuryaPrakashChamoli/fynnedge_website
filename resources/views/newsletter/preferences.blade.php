@php $status = session('newsletterStatus'); @endphp

<x-layouts.app title="Email preferences" robots="noindex, nofollow">
    <section class="mx-auto max-w-3xl px-6 py-16 lg:px-8">
        <x-ui.breadcrumbs :trail="['Email preferences' => null]" />

        <h1 class="mt-6 font-display text-3xl font-semibold tracking-tight text-ink sm:text-4xl">Email preferences</h1>
        <p class="mt-3 text-ink-muted">Choose what {{ $subscriber->email }} receives. Untick everything to stop all emails.</p>

        @if ($status)
            <x-ui.alert class="mt-5" tone="pass">{{ $status }}</x-ui.alert>
        @endif

        <form method="POST" action="{{ route('newsletter.preferences.update', ['token' => $token]) }}" class="mt-8">
            @csrf

            <div class="flex flex-col gap-3">
                @foreach ($categories as $category)
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-line bg-surface p-4 transition-colors hover:border-line-strong">
                        <input
                            type="checkbox"
                            name="categories[]"
                            value="{{ $category->value }}"
                            @checked($subscriber->acceptsCategory($category))
                            class="mt-1 h-4 w-4 rounded border-line-strong text-accent focus:ring-2 focus:ring-accent/30"
                        >
                        <span>
                            <span class="block text-sm font-medium text-ink">{{ $category->getLabel() }}</span>
                            <span class="mt-0.5 block text-sm text-ink-muted">{{ $category->getDescription() }}</span>
                        </span>
                    </label>
                @endforeach
            </div>

            <div class="mt-6 flex flex-wrap items-center gap-3">
                <x-ui.button type="submit">Save preferences</x-ui.button>
                <a href="{{ route('newsletter.unsubscribe', ['token' => $token]) }}" class="text-sm text-ink-muted underline transition-colors hover:text-ink">
                    Unsubscribe from everything
                </a>
            </div>
        </form>
    </section>
</x-layouts.app>
