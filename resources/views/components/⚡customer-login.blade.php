<?php

use App\Modules\Customers\Actions\LogInCustomer;
use App\Modules\Customers\Actions\ResolveCustomerAccount;
use App\Modules\MobileOtp\Actions\RequestMobileOtp;
use App\Modules\MobileOtp\Actions\VerifyMobileOtp;
use App\Modules\MobileOtp\Enums\MobileOtpPurpose;
use App\Modules\MobileOtp\Enums\MobileOtpStatus;
use App\Modules\MobileOtp\Enums\MobileOtpVerificationResult;
use App\Modules\MobileOtp\Exceptions\MobileOtpThrottled;
use App\Modules\MobileOtp\Models\MobileOtpChallenge;
use App\Modules\MobileOtp\Services\MobileNumber;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/*
 * Existing Customer sign-in: mobile number → OTP → the portal.
 *
 * Every well-formed number is sent a code and answered the same way, so the
 * form never says whether a number has an account; only after the code is
 * verified — when the visitor has proved they hold the number — does it say
 * that no account exists for it. Codes are issued and checked by the one OTP
 * system (purpose customer_login, which the public API refuses), and wrong
 * codes are limited per challenge AND per IP here, since Livewire actions
 * never pass through route throttles.
 */
new class extends Component
{
    /** @var 'mobile'|'otp'|'no_account' */
    #[Locked]
    public string $step = 'mobile';

    public string $mobileNumber = '';

    public string $otpCode = '';

    #[Locked]
    public ?string $otpChallengeId = null;

    /** The number the code was sent to — the input stays editable, this does not. */
    #[Locked]
    public ?string $challengedNumber = null;

    #[Locked]
    public int $resendAfterSeconds = 0;

    #[Computed]
    public function otpLength(): int
    {
        return MobileOtpChallenge::codeLength();
    }

    public function sendOtp(RequestMobileOtp $action): void
    {
        $this->mobileNumber = MobileNumber::normalize($this->mobileNumber);

        $this->validate([
            'mobileNumber' => ['required', 'regex:'.MobileNumber::PATTERN],
        ], [], ['mobileNumber' => __('mobile number')]);

        try {
            $challenge = $action->handle($this->mobileNumber, MobileOtpPurpose::CustomerLogin, request()->ip());
        } catch (MobileOtpThrottled $exception) {
            $this->addError($this->step === 'otp' ? 'otpCode' : 'mobileNumber', $exception->getMessage());

            return;
        }

        if ($challenge->status === MobileOtpStatus::Failed) {
            $this->addError($this->step === 'otp' ? 'otpCode' : 'mobileNumber', __('We could not send a verification code right now. Please try again shortly.'));

            return;
        }

        $this->otpChallengeId = $challenge->public_id;
        $this->challengedNumber = $this->mobileNumber;
        $this->resendAfterSeconds = $challenge->resendAvailableInSeconds();
        $this->otpCode = '';
        $this->step = 'otp';
    }

    public function verifyOtp(VerifyMobileOtp $verify, ResolveCustomerAccount $accounts, LogInCustomer $logIn): void
    {
        $this->validate(['otpCode' => ['required', 'digits:'.$this->otpLength]]);

        $throttleKey = 'customer-login:'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, (int) config('customer_portal.login_max_attempts'))) {
            $this->addError('otpCode', __('Too many attempts. Please try again in :minutes minutes.', ['minutes' => (int) ceil(RateLimiter::availableIn($throttleKey) / 60)]));

            return;
        }

        $challenge = MobileOtpChallenge::findForVerification($this->otpChallengeId, $this->challengedNumber, MobileOtpPurpose::CustomerLogin);
        $result = $challenge ? $verify->handle($challenge, $this->otpCode) : MobileOtpVerificationResult::NotFound;

        // Spent here, once: a replayed component state has nothing left to redeem.
        if (! $result->isVerified() || ! $challenge->consumeFor($this->challengedNumber, MobileOtpPurpose::CustomerLogin)) {
            RateLimiter::hit($throttleKey, (int) config('customer_portal.login_decay_minutes') * 60);

            $this->addError('otpCode', $result === MobileOtpVerificationResult::AttemptsExhausted
                ? $result->message()
                : __('That code is incorrect or has expired. You can resend a new one.'));

            return;
        }

        RateLimiter::clear($throttleKey);

        $customer = $accounts->forLogin($this->challengedNumber);

        if ($customer === null) {
            $this->step = 'no_account';

            return;
        }

        if (! $logIn->handle($customer, 'otp')) {
            $this->addError('otpCode', __('Access to your profile is switched off. Please contact us to restore it.'));

            return;
        }

        $this->redirect($this->intendedPortalUrl());
    }

    public function startOver(): void
    {
        $this->reset();
    }

    /**
     * Back to the portal page the customer was sent here from — only ever a
     * /my-profile URL on this site (the session value is set by the server,
     * but it is checked anyway) — else the dashboard.
     */
    private function intendedPortalUrl(): string
    {
        $intended = (string) session()->pull('url.intended', '');
        $portal = rtrim(route('portal.dashboard'), '/');

        return $intended === $portal || str_starts_with($intended, $portal.'/') ? $intended : route('portal.dashboard');
    }
};
?>

<x-ui.card>
    @if ($step === 'mobile')
        <form wire:submit="sendOtp" class="flex flex-col gap-4">
            <div>
                <label for="loginMobileNumber" class="text-sm font-medium text-ink">{{ __('Registered mobile number') }}</label>
                <input
                    id="loginMobileNumber"
                    type="tel"
                    inputmode="numeric"
                    autocomplete="tel-national"
                    maxlength="10"
                    placeholder="{{ __('10-digit mobile number') }}"
                    wire:model="mobileNumber"
                    class="mt-1.5 w-full rounded-lg border border-line-strong bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-faint focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/30"
                >
                @error('mobileNumber')
                    <p class="mt-1 text-xs text-warn">{{ $message }}</p>
                @else
                    <p class="mt-1.5 text-xs text-ink-faint">{{ __('We\'ll text a one-time code to this number. No password needed.') }}</p>
                @enderror
            </div>
            <x-ui.button type="submit" wire:loading.attr="disabled" class="w-full justify-center">{{ __('Send OTP') }}</x-ui.button>
        </form>
    @elseif ($step === 'otp')
        <form wire:submit="verifyOtp" class="flex flex-col gap-4">
            <p class="text-sm text-ink-muted">
                {!! __('If this number is registered with us, a :length-digit code is on its way to <span class="font-medium text-ink">+91 :number</span>.', ['length' => e($this->otpLength), 'number' => e(MobileNumber::mask((string) $challengedNumber))]) !!}
            </p>
            <div>
                <label for="loginOtpCode" class="text-sm font-medium text-ink">{{ __('Enter the :length-digit code', ['length' => $this->otpLength]) }}</label>
                <input
                    id="loginOtpCode"
                    type="text"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    maxlength="{{ $this->otpLength }}"
                    wire:model="otpCode"
                    class="mt-1.5 w-full rounded-lg border border-line-strong bg-surface px-3.5 py-2.5 text-sm tracking-[0.3em] text-ink focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/30"
                >
                @error('otpCode') <p class="mt-1 text-xs text-warn">{{ $message }}</p> @enderror
            </div>
            <div
                class="flex flex-wrap items-center gap-3"
                wire:key="login-resend-{{ $otpChallengeId }}"
                x-data="otpResendCountdown(@js($resendAfterSeconds))"
            >
                <x-ui.button type="submit" wire:loading.attr="disabled">{{ __('Verify & sign in') }}</x-ui.button>
                <x-ui.button type="button" wire:click="sendOtp" wire:loading.attr="disabled" ::disabled="resendIn > 0" variant="ghost" size="sm">
                    <span x-text="resendIn > 0 ? @js(__('Resend code in :time')).replace(':time', `${resendIn}s`) : @js(__('Resend code'))">{{ __('Resend code') }}</span>
                </x-ui.button>
                <x-ui.button type="button" wire:click="startOver" variant="ghost" size="sm">{{ __('Use another number') }}</x-ui.button>
            </div>
        </form>
    @else
        <div class="flex flex-col gap-4" data-no-account>
            <x-ui.badge tone="muted" class="self-start">{{ __('No profile yet') }}</x-ui.badge>
            <p class="text-sm text-ink-muted">{{ __('There is no profile for this number yet. Your profile is created when you check your credit score with us — it takes about two minutes, and your score is saved for next time.') }}</p>
            <div class="flex flex-col gap-2.5 sm:flex-row">
                <x-ui.button tag="a" href="{{ route('credit-score.show', 'cibil') }}" class="justify-center">{{ __('Check my credit score') }}</x-ui.button>
                <x-ui.button type="button" wire:click="startOver" variant="secondary" class="justify-center">{{ __('Try another number') }}</x-ui.button>
            </div>
        </div>
    @endif
</x-ui.card>
