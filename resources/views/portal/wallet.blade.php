<x-layouts.portal :title="__('Wallet & Plans')" :customer="$customer">
    @php
        $format = fn (int $paise): string => \App\Modules\Wallet\Services\Wallet::format($paise);
    @endphp

    @if ($errors->any())
        <x-ui.alert tone="warn" class="mb-6">{{ $errors->first() }}</x-ui.alert>
    @endif

    <div class="grid gap-4 sm:grid-cols-3">
        <x-ui.card data-summary="balance">
            <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">{{ __('Wallet balance') }}</p>
            <p class="mt-2 font-display text-3xl font-semibold text-ink">{{ $format($balance) }}</p>
        </x-ui.card>
        <x-ui.card data-summary="plan">
            <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">{{ __('Your plan') }}</p>
            @if ($activePlan)
                <p class="mt-2 font-display text-xl font-semibold text-ink">{{ $activePlan->plan->getLabel() }}</p>
                <p class="mt-1 text-xs text-ink-muted">{{ __('Active until :date', ['date' => $activePlan->ends_at->translatedFormat('d M Y')]) }}</p>
            @else
                <p class="mt-2 font-display text-xl font-semibold text-ink-faint">{{ __('No active plan') }}</p>
            @endif
            @foreach ($upcomingPlans as $upcoming)
                <p class="mt-1 text-xs text-ink-faint">{{ __(':plan starts :date', ['plan' => $upcoming->plan->getLabel(), 'date' => $upcoming->starts_at->translatedFormat('d M Y')]) }}</p>
            @endforeach
        </x-ui.card>
        <x-ui.card data-summary="free">
            <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">{{ __('Free check') }}</p>
            @if ($nextFreeCheckAt)
                <p class="mt-2 font-display text-xl font-semibold text-ink">{{ $nextFreeCheckAt->translatedFormat('d M Y') }}</p>
                <p class="mt-1 text-xs text-ink-muted">{{ __('Your next free check') }}</p>
            @else
                <p class="mt-2 font-display text-xl font-semibold text-pass">{{ __('Available now') }}</p>
            @endif
        </x-ui.card>
    </div>

    {{-- Prices --}}
    <x-ui.card class="mt-6">
        <h2 class="font-display text-lg font-semibold text-ink">{{ __('How refreshes are charged') }}</h2>
        <ul class="mt-3 flex list-disc flex-col gap-1.5 pl-5 text-sm text-ink-muted">
            <li>{{ __('Your first credit score check is free, and you get another free check every :months months.', ['months' => config('customer_portal.billing.free_check_every_months')]) }}</li>
            <li>{{ __('After that, your first refresh costs :price + GST (:total), and each later refresh :later + GST (:later_total).', [
                'price' => $format($firstRefreshPrice->taxablePaise),
                'total' => $format($firstRefreshPrice->totalPaise()),
                'later' => $format($refreshPrice->taxablePaise),
                'later_total' => $format($refreshPrice->totalPaise()),
            ]) }}</li>
            <li>{{ __('A refreshed report stays current for 30 days, so each bureau can be refreshed once every 30 days.') }}</li>
            <li>{{ __('You are only charged when the bureau returns a new result — if a check fails, the amount goes straight back to your wallet.') }}</li>
        </ul>
        @unless ($billingEnabled)
            <x-ui.alert class="mt-4">{{ __('Refreshes are free for now — paid refreshes have not started yet.') }}</x-ui.alert>
        @endunless
    </x-ui.card>

    {{-- Plans --}}
    <x-ui.card class="mt-6" id="plans">
        <h2 class="font-display text-lg font-semibold text-ink">{{ __('Refresh plans') }}</h2>
        <p class="mt-1 text-sm text-ink-muted">{{ __('With a plan you can refresh every bureau once every 30 days at no extra charge. A plan bought while another is active starts when that one ends.') }}</p>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            @foreach ($plans as $value => $offer)
                <div class="flex flex-col rounded-xl border border-line p-4" data-plan="{{ $value }}">
                    <p class="font-medium text-ink">{{ $offer['plan']->getLabel() }}</p>
                    <p class="mt-1 font-display text-2xl font-semibold text-ink">{{ $format($offer['price']->taxablePaise) }} <span class="text-sm font-normal text-ink-faint">+ {{ __('GST') }}</span></p>
                    <p class="text-xs text-ink-muted">{{ __(':total including GST, paid from your wallet', ['total' => $format($offer['price']->totalPaise())]) }}</p>
                    @if ($billingEnabled)
                        <form method="POST" action="{{ route('portal.wallet.plans.buy', $value) }}" class="mt-4">
                            @csrf
                            <x-ui.button type="submit" size="sm" :variant="$balance >= $offer['price']->totalPaise() ? 'primary' : 'secondary'" class="w-full justify-center">{{ __('Buy with wallet') }}</x-ui.button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    </x-ui.card>

    {{-- UPI top-up --}}
    <x-ui.card class="mt-6" id="top-up">
        <h2 class="font-display text-lg font-semibold text-ink">{{ __('Add money by UPI') }}</h2>
        @if ($topUpsAvailable)
            <form method="POST" action="{{ route('portal.wallet.top-up') }}" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                @csrf
                <div>
                    <label for="amount" class="text-sm font-medium text-ink">{{ __('Amount (₹)') }}</label>
                    <input id="amount" name="amount" type="number" inputmode="numeric" min="{{ intdiv((int) config('customer_portal.payments.top_up_min_paise'), 100) }}" max="{{ intdiv((int) config('customer_portal.payments.top_up_max_paise'), 100) }}" value="{{ old('amount', 200) }}" class="mt-1.5 w-full rounded-lg border border-line-strong bg-surface px-3.5 py-2.5 text-sm text-ink focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/30 sm:w-48">
                </div>
                <x-ui.button type="submit">{{ __('Pay with UPI') }}</x-ui.button>
            </form>
            <p class="mt-2 text-xs text-ink-faint">{{ __('Your wallet is credited as soon as the payment is confirmed. The balance can only be used for credit score refreshes and plans.') }}</p>
        @else
            <p class="mt-2 text-sm text-ink-muted">{{ __('UPI top-ups are not available yet. Please contact support to add money to your wallet.') }}</p>
            <a href="{{ route('portal.support') }}" class="mt-2 inline-block text-sm font-medium text-accent underline">{{ __('Contact support') }}</a>
        @endif
    </x-ui.card>

    {{-- Ledger --}}
    <x-ui.card class="mt-6">
        <h2 class="font-display text-lg font-semibold text-ink">{{ __('Wallet history') }}</h2>
        @if ($transactions->isEmpty())
            <x-portal.empty-state class="mt-4" :title="__('No wallet activity yet')" />
        @else
            <div class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[34rem] text-left text-sm" data-wallet-history>
                    <thead class="text-xs text-ink-faint">
                        <tr>
                            <th class="py-2 pr-3 font-medium">{{ __('Date') }}</th>
                            <th class="py-2 pr-3 font-medium">{{ __('Description') }}</th>
                            <th class="py-2 pr-3 font-medium">{{ __('Status') }}</th>
                            <th class="py-2 text-right font-medium">{{ __('Amount') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($transactions as $transaction)
                            <tr>
                                <td class="py-2.5 pr-3 text-ink-muted">{{ $transaction->created_at->translatedFormat('d M Y') }}</td>
                                <td class="py-2.5 pr-3 text-ink">
                                    {{ $transaction->type->getLabel() }}
                                    @if ($transaction->gst_paise)
                                        <span class="block text-xs text-ink-faint">{{ __(':price + GST :gst', ['price' => $format($transaction->taxable_paise), 'gst' => $format($transaction->gst_paise)]) }}</span>
                                    @endif
                                </td>
                                <td class="py-2.5 pr-3"><x-ui.badge :tone="match ($transaction->status) { \App\Modules\Wallet\Enums\WalletTransactionStatus::Completed => 'pass', \App\Modules\Wallet\Enums\WalletTransactionStatus::Held => 'warn', default => 'muted' }">{{ $transaction->status->getLabel() }}</x-ui.badge></td>
                                <td @class(['py-2.5 text-right font-mono', 'text-pass' => $transaction->amount_paise > 0, 'text-ink' => $transaction->amount_paise <= 0])>{{ $format($transaction->amount_paise) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $transactions->links() }}</div>
        @endif
    </x-ui.card>
</x-layouts.portal>
