<?php

namespace App\Http\Controllers\Portal;

use App\Modules\Wallet\Actions\BuyCustomerPlan;
use App\Modules\Wallet\Actions\StartWalletTopUp;
use App\Modules\Wallet\Contracts\PaymentGateway;
use App\Modules\Wallet\DataTransferObjects\Price;
use App\Modules\Wallet\Enums\PlanType;
use App\Modules\Wallet\Exceptions\InsufficientWalletBalance;
use App\Modules\Wallet\Services\CreditCheckBilling;
use App\Modules\Wallet\Services\Wallet;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wallet & Plans: the balance, what a refresh costs, the plans, UPI top-ups
 * (only once a payment gateway is configured) and the ledger.
 */
class WalletController extends PortalController
{
    public function show(Request $request, Wallet $wallet, CreditCheckBilling $billing, PaymentGateway $gateway): View
    {
        $customer = $this->customer($request);

        return view('portal.wallet', [
            'customer' => $customer,
            'billingEnabled' => CreditCheckBilling::enabled(),
            'balance' => $wallet->balance($customer),
            'activePlan' => $billing->activePlan($customer),
            'upcomingPlans' => $customer->plans()->where('starts_at', '>', now())->orderBy('starts_at')->get(),
            'nextFreeCheckAt' => $billing->nextFreeCheckAt($customer),
            'firstRefreshPrice' => Price::withGst((int) config('customer_portal.billing.first_refresh_paise')),
            'refreshPrice' => Price::withGst((int) config('customer_portal.billing.refresh_paise')),
            'plans' => collect(PlanType::cases())->mapWithKeys(fn (PlanType $plan): array => [$plan->value => ['plan' => $plan, 'price' => Price::withGst($plan->pricePaise())]]),
            'topUpsAvailable' => $gateway->isConfigured(),
            'transactions' => $customer->walletTransactions()->latest()->orderByDesc('id')->paginate(15),
        ]);
    }

    public function buyPlan(Request $request, PlanType $plan, BuyCustomerPlan $action): RedirectResponse
    {
        abort_unless(CreditCheckBilling::enabled(), 404);

        try {
            $bought = $action->handle($this->customer($request), $plan);
        } catch (InsufficientWalletBalance $exception) {
            return redirect()->route('portal.wallet')->withErrors(['plan' => __('Your wallet balance (:balance) does not cover this plan (:total). Please add money first.', [
                'balance' => Wallet::format($exception->balancePaise),
                'total' => Wallet::format($exception->requiredPaise),
            ])]);
        }

        return redirect()->route('portal.wallet')->with('status', __(':plan active until :date.', [
            'plan' => $plan->getLabel(),
            'date' => $bought->ends_at->translatedFormat('d M Y'),
        ]));
    }

    public function topUp(Request $request, StartWalletTopUp $action, PaymentGateway $gateway): Response
    {
        if (! $gateway->isConfigured()) {
            return redirect()->route('portal.wallet')->withErrors(['amount' => __('UPI top-ups are not available yet. Please contact support to add money to your wallet.')]);
        }

        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:'.intdiv((int) config('customer_portal.payments.top_up_min_paise'), 100), 'max:'.intdiv((int) config('customer_portal.payments.top_up_max_paise'), 100)],
        ], [], ['amount' => __('amount')]);

        $started = $action->handle($this->customer($request), (int) $validated['amount'] * 100);

        // The gateway's own checkout page; the wallet is credited only when the
        // gateway confirms the payment server-side, never by this redirect.
        return redirect()->away($started['checkoutUrl']);
    }
}
