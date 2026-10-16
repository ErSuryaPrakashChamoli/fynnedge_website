<x-layouts.app :title="__('Existing Customer — Sign in').' | '.config('customer_portal.brand')" robots="noindex, nofollow">
    <section class="mx-auto max-w-7xl px-6 py-14 lg:px-8">
        <div class="grid gap-12 lg:grid-cols-[1.1fr_1fr] lg:items-start">
            <div>
                <x-ui.badge tone="accent">{{ config('customer_portal.brand') }}</x-ui.badge>
                <h1 class="mt-4 text-balance font-display text-3xl font-semibold tracking-tight text-ink sm:text-4xl">{{ __('Existing Customer') }}</h1>
                <p class="mt-3 max-w-lg text-ink-muted">{{ __('Sign in with your registered mobile number to see your saved credit scores and reports, keep your details up to date and apply for a loan.') }}</p>

                <ul class="mt-8 flex flex-col gap-3 text-sm text-ink-muted">
                    @foreach ([
                        __('Signing in never runs a new credit check — your saved scores and reports open instantly.'),
                        __('We sign you in with a one-time code sent by SMS. We never ask for a password, and our team will never ask you for your code.'),
                        __('Your PAN and reports are stored encrypted and shown only to you once you sign in.'),
                    ] as $point)
                        <li class="flex items-start gap-3">
                            <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-pass-soft text-pass">
                                <svg viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4L8 11.6l6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" /></svg>
                            </span>
                            {{ $point }}
                        </li>
                    @endforeach
                </ul>

                <p class="mt-8 text-sm text-ink-muted">
                    {{ __('New here?') }}
                    <a href="{{ route('credit-score.show', 'cibil') }}" class="font-medium text-accent underline">{{ __('Check your free credit score') }}</a>
                    {{ __('— your profile is created as you do.') }}
                </p>
                <p class="mt-2 text-xs text-ink-faint">
                    {!! __('Read our <a href=":privacy_policy_url" class="underline hover:text-ink">Privacy Policy</a> and <a href=":terms_url" class="underline hover:text-ink">Terms</a>.', ['privacy_policy_url' => e(route('privacy-policy')), 'terms_url' => e(route('terms'))]) !!}
                </p>
            </div>

            <div class="lg:sticky lg:top-24">
                @if (session('status'))
                    <x-ui.alert tone="accent" class="mb-4">{{ session('status') }}</x-ui.alert>
                @endif
                <livewire:customer-login />
            </div>
        </div>
    </section>
</x-layouts.app>
