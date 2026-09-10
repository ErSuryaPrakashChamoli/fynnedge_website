@php
    $siteName = \App\Models\Setting::get('site_name', 'FynnEdge');

    [$heading, $message, $tone] = match ($result) {
        \App\Modules\Newsletter\Actions\ConfirmSubscription::RESULT_CONFIRMED => [
            'You are subscribed',
            "Thanks for confirming. You'll now receive practical financial insights, loan tips and useful updates from {$siteName}.",
            'pass',
        ],
        \App\Modules\Newsletter\Actions\ConfirmSubscription::RESULT_ALREADY_CONFIRMED => [
            'Already confirmed',
            'This email address is already subscribed — there is nothing more to do.',
            'accent',
        ],
        \App\Modules\Newsletter\Actions\ConfirmSubscription::RESULT_EXPIRED => [
            'This link has expired',
            'Confirmation links are valid for '.\App\Modules\Newsletter\Models\NewsletterSubscriber::CONFIRMATION_TTL_DAYS.' days. Subscribe again below and we will send you a fresh one.',
            'warn',
        ],
        default => [
            'This link is not valid',
            'It may have already been used, or the address may have been copied incompletely. You can subscribe again below.',
            'warn',
        ],
    };
@endphp

<x-layouts.app title="Newsletter" robots="noindex, nofollow">
    <section class="mx-auto max-w-3xl px-6 py-16 lg:px-8">
        <x-ui.breadcrumbs :trail="['Newsletter' => null]" />

        <h1 class="mt-6 font-display text-3xl font-semibold tracking-tight text-ink sm:text-4xl">{{ $heading }}</h1>

        <x-ui.alert class="mt-5" :tone="$tone">{{ $message }}</x-ui.alert>

        @if ($result === \App\Modules\Newsletter\Actions\ConfirmSubscription::RESULT_CONFIRMED)
            <div class="mt-8 flex flex-wrap gap-3">
                <x-ui.button tag="a" :href="route('resources.index')">Read the latest articles</x-ui.button>
                <x-ui.button tag="a" variant="secondary" :href="route('home')">Back to the homepage</x-ui.button>
            </div>
        @else
            <x-site.newsletter-form
                class="mt-8"
                source="website"
                heading="Subscribe to FynnEdge Insights"
                description="Practical financial insights, loan tips and useful money guidance — straight to your inbox."
            />
        @endif
    </section>
</x-layouts.app>
