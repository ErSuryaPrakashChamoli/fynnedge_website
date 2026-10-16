<x-layouts.portal :title="__('Account Settings')" :customer="$customer">
    <div class="flex flex-col gap-6">
        <x-ui.card>
            <h2 class="font-display text-lg font-semibold text-ink">{{ __('Sign-in') }}</h2>
            <dl class="mt-4 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-ink-faint">{{ __('Registered mobile') }}</dt>
                    <dd class="font-mono text-ink">+91 {{ $customer->maskedMobile() }}</dd>
                </div>
                <div>
                    <dt class="text-ink-faint">{{ __('Sign-in method') }}</dt>
                    <dd class="text-ink">{{ __('One-time code by SMS') }}</dd>
                </div>
                <div>
                    <dt class="text-ink-faint">{{ __('Account opened') }}</dt>
                    <dd class="text-ink">{{ $customer->portal_activated_at?->translatedFormat('d M Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-ink-faint">{{ __('Last sign-in') }}</dt>
                    <dd class="text-ink">{{ $customer->last_login_at?->translatedFormat('d M Y, h:i A') ?? '—' }}</dd>
                </div>
            </dl>
            <p class="mt-4 text-sm text-ink-muted">{{ __('Your registered mobile number is how you sign in, so it can only be changed by our team after checking it is you. Please contact support.') }}</p>
        </x-ui.card>

        <x-ui.card>
            <h2 class="font-display text-lg font-semibold text-ink">{{ __('Security') }}</h2>
            <ul class="mt-3 flex list-disc flex-col gap-1.5 pl-5 text-sm text-ink-muted">
                <li>{{ __('We will never ask for your one-time code by phone, email or chat.') }}</li>
                <li>{{ __('Signing in or opening a report never runs a credit check. A new check always asks for your consent and a fresh code.') }}</li>
                <li>{{ __('On a shared or public computer, always sign out when you finish.') }}</li>
            </ul>
            <form method="POST" action="{{ route('portal.logout') }}" class="mt-4">
                @csrf
                <x-ui.button type="submit" variant="secondary" size="sm">{{ __('Sign out of this device') }}</x-ui.button>
            </form>
        </x-ui.card>

        <x-ui.card>
            <h2 class="font-display text-lg font-semibold text-ink">{{ __('Your data') }}</h2>
            <p class="mt-3 text-sm text-ink-muted">
                {!! __('How we use and protect your information is explained in our <a href=":privacy_policy_url" class="text-accent underline">Privacy Policy</a>. To ask for a copy of your data, a correction or account closure, <a href=":support_url" class="text-accent underline">contact support</a>.', ['privacy_policy_url' => e(route('privacy-policy')), 'support_url' => e(route('portal.support'))]) !!}
            </p>
        </x-ui.card>
    </div>
</x-layouts.portal>
