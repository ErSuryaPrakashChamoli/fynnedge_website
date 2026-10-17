<x-layouts.portal :title="__('Refresh my :bureau score', ['bureau' => $bureau->getLabel()])" :customer="$customer">
    <div class="grid gap-8 lg:grid-cols-[1fr_1.1fr] lg:items-start">
        <div class="flex flex-col gap-4 text-sm text-ink-muted">
            <p>{{ __('A refresh asks :bureau for your latest score. We send a one-time code to your registered mobile and ask for your consent again — signing in alone never starts a credit check.', ['bureau' => $bureau->getLabel()]) }}</p>
            @if ($eligibility->latestSuccess)
                <p>{{ __('Your last saved report is from :date. It stays available whatever the result of this refresh.', ['date' => $eligibility->latestSuccess->completed_at->translatedFormat('d M Y')]) }}</p>
            @endif
            <a href="{{ route('portal.credit-score') }}" class="font-medium text-accent underline">{{ __('Back to my credit score') }}</a>
        </div>

        <div>
            @if ($eligibility->isAvailable())
                @if ($quote->needsTopUp())
                    <x-ui.alert tone="warn" class="mb-4" data-payment-required>
                        {{ __('This refresh costs :total including GST and your wallet balance is :balance.', ['total' => \App\Modules\Wallet\Services\Wallet::format($quote->price->totalPaise()), 'balance' => \App\Modules\Wallet\Services\Wallet::format($quote->balancePaise)]) }}
                        <a href="{{ route('portal.wallet') }}" class="font-medium underline">{{ __('Add money or buy a plan') }}</a>
                    </x-ui.alert>
                @endif
                <livewire:credit-score-check :bureau="$bureau->value" context="portal" />
            @else
                <x-portal.refresh-status :eligibility="$eligibility" :quote="$quote" />
            @endif
        </div>
    </div>
</x-layouts.portal>
