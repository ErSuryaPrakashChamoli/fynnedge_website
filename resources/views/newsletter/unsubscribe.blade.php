<x-layouts.app :title="__('Unsubscribe')" robots="noindex, nofollow">
    <section class="mx-auto max-w-7xl px-6 py-16 lg:px-8">
        <x-ui.breadcrumbs :trail="[__('Newsletter') => null]" />

        @if ($subscriber)
            <h1 class="mt-6 font-display text-3xl font-semibold tracking-tight text-ink sm:text-4xl">{{ __('You have been unsubscribed') }}</h1>

            <x-ui.alert class="mt-5" tone="pass">
                {{ __(':email has been removed from our newsletter. You will not receive any further emails from this list.', ['email' => $subscriber->email]) }}
            </x-ui.alert>

            {{--
                Offering preferences instead of only "all or nothing" is what stops
                someone who just wanted fewer emails from leaving entirely.
            --}}
            <p class="mt-6 text-sm text-ink-muted">
                {!! __('Left by mistake, or only wanted fewer emails? <a href=":url" class="font-medium text-accent underline">Choose what you receive instead</a>.', ['url' => e(route('newsletter.preferences', ['token' => $token]))]) !!}
            </p>
        @else
            <h1 class="mt-6 font-display text-3xl font-semibold tracking-tight text-ink sm:text-4xl">{{ __('This link is not valid') }}</h1>

            <x-ui.alert class="mt-5" tone="warn">
                {{ __('We could not find a subscription for this link. It may have been copied incompletely, or the subscription may already have been removed.') }}
            </x-ui.alert>

            <p class="mt-6 text-sm text-ink-muted">
                {{ __('If you are still receiving emails, reply to any of them and we will remove you manually.') }}
            </p>
        @endif
    </section>
</x-layouts.app>
