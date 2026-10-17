<x-layouts.portal :title="__('Help & Support')" :customer="$customer">
    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card>
            <h2 class="font-display text-lg font-semibold text-ink">{{ __('Talk to us') }}</h2>
            <p class="mt-2 text-sm text-ink-muted">{{ __('For questions about your profile, a credit report or a loan application, reach our team here. Please do not share your one-time code with anyone.') }}</p>
            <ul class="mt-4 flex flex-col gap-2 text-sm">
                @if ($contactPhone)
                    <li><span class="text-ink-faint">{{ __('Phone') }}:</span> <a href="tel:{{ preg_replace('/[^0-9+]/', '', $contactPhone) }}" class="font-medium text-accent underline">{{ $contactPhone }}</a></li>
                @endif
                @if ($contactEmail)
                    <li><span class="text-ink-faint">{{ __('Email') }}:</span> <a href="mailto:{{ $contactEmail }}" class="font-medium text-accent underline">{{ $contactEmail }}</a></li>
                @endif
                @if ($contactWhatsapp)
                    <li><span class="text-ink-faint">{{ __('WhatsApp') }}:</span> <span class="font-medium text-ink">{{ $contactWhatsapp }}</span></li>
                @endif
            </ul>
            <x-ui.button tag="a" :href="route('contact')" class="mt-5">{{ __('Send us a message') }}</x-ui.button>
        </x-ui.card>

        <x-ui.card>
            <h2 class="font-display text-lg font-semibold text-ink">{{ __('Common questions') }}</h2>
            <dl class="mt-4 flex flex-col gap-4 text-sm">
                <div>
                    <dt class="font-medium text-ink">{{ __('Does signing in or viewing my report affect my score?') }}</dt>
                    <dd class="mt-1 text-ink-muted">{{ __('No. Signing in and opening saved reports never contact a credit bureau. Checking your own score does not lower it either.') }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-ink">{{ __('Why do bureaus show different scores?') }}</dt>
                    <dd class="mt-1 text-ink-muted">{{ __('Each bureau uses its own data and scoring model, so compare a score only with earlier scores from the same bureau.') }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-ink">{{ __('Something in my credit report is wrong.') }}</dt>
                    <dd class="mt-1 text-ink-muted">{{ __('Report data comes from your lenders through the bureau. Raise a dispute with the bureau or the lender that reported it; we can guide you.') }}</dd>
                </div>
            </dl>
            <a href="{{ route('faqs.index') }}" class="mt-4 inline-block text-sm font-medium text-accent underline">{{ __('More FAQs') }}</a>
        </x-ui.card>
    </div>

    @if ($grievanceLevels->isNotEmpty())
        <x-ui.card class="mt-6">
            <h2 class="font-display text-lg font-semibold text-ink">{{ __('Grievance redressal') }}</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                @foreach ($grievanceLevels as $level)
                    <div class="rounded-xl border border-line p-4 text-sm">
                        <p class="font-medium text-ink">{{ $level->level }}</p>
                        <p class="mt-1 text-ink-muted">{{ $level->contact_name }}@if ($level->designation), {{ $level->designation }}@endif</p>
                        @if ($level->email)<p class="mt-1"><a href="mailto:{{ $level->email }}" class="text-accent underline">{{ $level->email }}</a></p>@endif
                        @if ($level->phone)<p class="mt-1 text-ink-muted">{{ $level->phone }}</p>@endif
                        @if ($level->turnaround_time)<p class="mt-1 text-xs text-ink-faint">{{ __('Response time: :time', ['time' => $level->turnaround_time]) }}</p>@endif
                    </div>
                @endforeach
            </div>
        </x-ui.card>
    @endif
</x-layouts.portal>
