<x-layouts.app title="Unsubscribe" robots="noindex, nofollow">
    <section class="mx-auto max-w-3xl px-6 py-16 lg:px-8">
        <x-ui.breadcrumbs :trail="['Newsletter' => null]" />

        @if ($subscriber)
            <h1 class="mt-6 font-display text-3xl font-semibold tracking-tight text-ink sm:text-4xl">You have been unsubscribed</h1>

            <x-ui.alert class="mt-5" tone="pass">
                {{ $subscriber->email }} has been removed from our newsletter. You will not receive any further emails from this list.
            </x-ui.alert>

            {{--
                Offering preferences instead of only "all or nothing" is what stops
                someone who just wanted fewer emails from leaving entirely.
            --}}
            <p class="mt-6 text-sm text-ink-muted">
                Left by mistake, or only wanted fewer emails?
                <a href="{{ route('newsletter.preferences', ['token' => $token]) }}" class="font-medium text-accent underline">
                    Choose what you receive instead
                </a>.
            </p>
        @else
            <h1 class="mt-6 font-display text-3xl font-semibold tracking-tight text-ink sm:text-4xl">This link is not valid</h1>

            <x-ui.alert class="mt-5" tone="warn">
                We could not find a subscription for this link. It may have been copied incompletely, or the subscription may already have been removed.
            </x-ui.alert>

            <p class="mt-6 text-sm text-ink-muted">
                If you are still receiving emails, reply to any of them and we will remove you manually.
            </p>
        @endif
    </section>
</x-layouts.app>
