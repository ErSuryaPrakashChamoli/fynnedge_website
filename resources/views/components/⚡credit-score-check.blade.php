<?php

use App\Modules\CreditBureau\Enums\CreditCheckStatus;
use App\Modules\CreditBureau\Enums\CrifErrorCode;
use App\Modules\CreditBureau\Services\CrifSettings;
use App\Modules\CreditBureau\Services\PanName;
use App\Modules\CreditScore\Actions\SubmitCreditScoreCheck;
use App\Modules\CreditScore\Enums\BureauName;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use App\Modules\MobileOtp\Actions\RequestMobileOtp;
use App\Modules\MobileOtp\Actions\VerifyMobileOtp;
use App\Modules\MobileOtp\Enums\MobileOtpPurpose;
use App\Modules\MobileOtp\Enums\MobileOtpStatus;
use App\Modules\MobileOtp\Enums\MobileOtpVerificationResult;
use App\Modules\MobileOtp\Exceptions\MobileOtpThrottled;
use App\Modules\MobileOtp\Models\MobileOtpChallenge;
use App\Modules\MobileOtp\Services\MobileNumber;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    public string $bureau;

    /**
     * Locked: every public property is otherwise writable from the browser, and
     * an unlocked step let a visitor jump straight to 'details' without a code.
     *
     * @var 'mobile'|'otp'|'details'|'result'
     */
    #[Locked]
    public string $step = 'mobile';

    public string $mobileNumber = '';

    public string $otpCode = '';

    /** The challenge's public_id — the internal row id never reaches the browser. */
    #[Locked]
    public ?string $otpChallengeId = null;

    /**
     * The number the code was verified for. mobileNumber stays wire:model-bound,
     * so the check is run against this locked copy, never against whatever the
     * input holds by the time the details are submitted.
     */
    #[Locked]
    public ?string $verifiedMobileNumber = null;

    #[Locked]
    public int $resendAfterSeconds = 0;

    public string $fullName = '';

    public string $dateOfBirth = '';

    public string $panNumber = '';

    public bool $consent = false;

    public ?int $resultScore = null;

    /**
     * What the result step shows: 'demo' (sample score), 'live' (a real CRIF
     * score), 'no_score' (CRIF answered but holds no score), 'pending' (outcome
     * not confirmed yet) or 'failed'. Locked: the browser must never be able to
     * dress a demo score up as a real one.
     */
    #[Locked]
    public ?string $resultKind = null;

    /** The CrifErrorCode value behind a failed live check, for the visitor's message. */
    #[Locked]
    public ?string $resultError = null;

    /**
     * Accepts a plain string (from the `:bureau="$bureau->value"` the controller
     * passes in) rather than requiring a typed BureauName — Livewire's test
     * harness assigns initial params to typed public properties directly, before
     * mount() runs, so a strict enum type here would reject the raw string it's
     * given. Mirrors the same accepts-string pattern used by the EMI calculator.
     */
    public function mount(string $bureau): void
    {
        $this->bureau = (BureauName::tryFrom($bureau) ?? BureauName::Cibil)->value;
    }

    #[Computed]
    public function bureauLabel(): string
    {
        return BureauName::from($this->bureau)->getLabel();
    }

    /**
     * The CRIF page shows real scores (a paid Surepass call per new check)
     * only while CRIF Settings → "Free CRIF Score page uses real scores" is on
     * and the server has a Surepass token. Every other bureau stays a demo.
     */
    #[Computed]
    public function isLiveCrif(): bool
    {
        return $this->bureau === BureauName::Crif->value && CrifSettings::publicScoreLive();
    }

    #[Computed]
    public function stepIndex(): int
    {
        return match ($this->step) {
            'mobile' => 0,
            'otp' => 1,
            'details' => 2,
            'result' => 3,
        };
    }

    #[Computed]
    public function otpLength(): int
    {
        return MobileOtpChallenge::codeLength();
    }

    #[Computed]
    public function maskedMobileNumber(): string
    {
        return MobileNumber::mask($this->mobileNumber);
    }

    public function sendOtp(RequestMobileOtp $action): void
    {
        $this->mobileNumber = MobileNumber::normalize($this->mobileNumber);

        $this->validate([
            'mobileNumber' => ['required', 'regex:'.MobileNumber::PATTERN],
        ], [], ['mobileNumber' => __('mobile number')]);

        try {
            $challenge = $action->handle($this->mobileNumber, MobileOtpPurpose::CreditScoreCheck, request()->ip());
        } catch (MobileOtpThrottled $exception) {
            $this->addError($this->step === 'otp' ? 'otpCode' : 'mobileNumber', $exception->getMessage());

            return;
        }

        if ($challenge->status === MobileOtpStatus::Failed) {
            $this->addError($this->step === 'otp' ? 'otpCode' : 'mobileNumber', __('We could not send a verification code right now. Please try again shortly.'));

            return;
        }

        $this->otpChallengeId = $challenge->public_id;
        $this->verifiedMobileNumber = null;
        $this->resendAfterSeconds = $challenge->resendAvailableInSeconds();
        $this->otpCode = '';
        $this->step = 'otp';
    }

    public function resendOtp(RequestMobileOtp $action): void
    {
        $this->sendOtp($action);
    }

    public function verifyOtp(VerifyMobileOtp $action): void
    {
        $this->validate([
            'otpCode' => ['required', 'digits:'.$this->otpLength],
        ]);

        // Matched on the number too: editing the input after the code was sent
        // can never verify a different number than the one that received it.
        $challenge = MobileOtpChallenge::findForVerification($this->otpChallengeId, $this->mobileNumber, MobileOtpPurpose::CreditScoreCheck);
        $result = $challenge ? $action->handle($challenge, $this->otpCode) : MobileOtpVerificationResult::NotFound;

        if (! $result->isVerified()) {
            $this->addError('otpCode', $result === MobileOtpVerificationResult::AttemptsExhausted
                ? $result->message()
                : __('That code is incorrect or has expired. You can resend a new one.'));

            return;
        }

        $this->verifiedMobileNumber = $challenge->mobile_number;
        $this->step = 'details';
    }

    /** "SURYA prakash chamoli" → "Surya Prakash Chamoli", whichever case it was typed in. */
    public function updatedFullName(string $value): void
    {
        $this->fullName = PanName::normalize($value);
    }

    public function updatedPanNumber(string $value): void
    {
        $this->panNumber = strtoupper($value);
    }

    public function submitDetails(SubmitCreditScoreCheck $action): void
    {
        if ($this->step !== 'details' || $this->verifiedMobileNumber === null) {
            $this->restart();

            return;
        }

        $validated = $this->validate([
            'fullName' => ['required', 'string', 'max:120'],
            'dateOfBirth' => ['required', 'date', 'before:-18 years'],
            'panNumber' => ['required', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/'],
            'consent' => ['accepted'],
        ], [
            'dateOfBirth.before' => __('You must be at least 18 years old.'),
            'panNumber.regex' => __('Enter a valid PAN (e.g. ABCDE1234F).'),
            'consent.accepted' => __('Please provide consent to continue.'),
        ]);

        // CRIF matches on first AND last name; a single word cannot be sent.
        if ($this->isLiveCrif && count(preg_split('/\s+/', trim($validated['fullName'])) ?: []) < 2) {
            $this->addError('fullName', __('Enter your first and last name as on your PAN card.'));

            return;
        }

        // The verification is spent on exactly one check — re-submitting, or a
        // second tab replaying the same component state, has nothing left to spend.
        $challenge = MobileOtpChallenge::findForVerification($this->otpChallengeId, $this->verifiedMobileNumber, MobileOtpPurpose::CreditScoreCheck);

        if (! $challenge?->consumeFor($this->verifiedMobileNumber, MobileOtpPurpose::CreditScoreCheck)) {
            $this->restart();
            $this->addError('mobileNumber', __('Your verification has expired. Please verify your mobile number again.'));

            return;
        }

        $check = $action->handle(
            [
                'full_name' => $validated['fullName'],
                'date_of_birth' => $validated['dateOfBirth'],
                'pan_number' => $validated['panNumber'],
            ],
            BureauName::from($this->bureau),
            $this->verifiedMobileNumber,
            request()->ip(),
        );

        $this->resultScore = $check->score;
        $this->resultError = is_array($check->raw_response) ? ($check->raw_response['error'] ?? null) : null;
        $this->resultKind = match (true) {
            $check->provider !== 'surepass' => 'demo',
            $check->status === CreditCheckStatus::Completed && $check->score !== null => 'live',
            $check->status === CreditCheckStatus::Completed => 'no_score',
            $check->status === CreditCheckStatus::Pending => 'pending',
            default => 'failed',
        };
        $this->step = 'result';
    }

    public function restart(): void
    {
        $this->reset([
            'step', 'mobileNumber', 'otpCode', 'otpChallengeId', 'verifiedMobileNumber', 'resendAfterSeconds',
            'fullName', 'dateOfBirth', 'panNumber', 'consent', 'resultScore', 'resultKind', 'resultError',
        ]);
    }
};
?>

<x-ui.card :padded="false" class="overflow-hidden">
    @if ($step === 'mobile')
        <div class="flex items-center gap-2.5 bg-accent-soft px-6 py-3 text-sm text-accent-strong">
            <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 shrink-0" aria-hidden="true"><path d="M10 2a6 6 0 0 0-6 6c0 4-2 5.5-2 5.5h16S16 12 16 8a6 6 0 0 0-6-6ZM8.5 16a1.5 1.5 0 0 0 3 0h-3Z" /></svg>
            {{ __('Check your free :bureau score in under 2 minutes', ['bureau' => $this->bureauLabel]) }}
        </div>
    @endif

    <div class="p-6 sm:p-7">
        <x-ui.stepper :steps="[__('Mobile number'), __('Verify OTP'), __('Your details'), __('Your score')]" :current="$this->stepIndex" />

        <div class="mt-7">
            @if ($step === 'mobile')
                <p class="font-display text-xl font-semibold text-ink">{{ __('Let\'s get started') }}</p>
                <div class="mt-4 flex flex-col gap-4">
                    <div>
                        <label for="mobileNumber" class="text-sm font-medium text-ink">{{ __('Mobile number') }}</label>
                        <input
                            id="mobileNumber"
                            type="tel"
                            inputmode="numeric"
                            maxlength="10"
                            placeholder="{{ __('10-digit mobile number') }}"
                            wire:model="mobileNumber"
                            class="mt-1.5 w-full rounded-lg border border-line-strong bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-faint focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/30"
                        >
                        @error('mobileNumber')
                            <p class="mt-1 text-xs text-warn">{{ $message }}</p>
                        @else
                            <p class="mt-1.5 text-xs text-ink-faint">{{ __('You\'ll receive an OTP on this number.') }}</p>
                        @enderror
                    </div>
                    <p class="text-xs text-ink-faint">
                        {!! __('By continuing, you agree to FynnEdge\'s <a href=":terms_url" class="underline hover:text-ink">Terms</a> and <a href=":privacy_policy_url" class="underline hover:text-ink">Privacy Policy</a>.', ['terms_url' => e(route('terms')), 'privacy_policy_url' => e(route('privacy-policy'))]) !!}
                    </p>
                    <x-ui.button wire:click="sendOtp" wire:loading.attr="disabled" class="w-full justify-center">
                        {{ __('Get Free :bureau Score', ['bureau' => $this->bureauLabel]) }}
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" class="h-3.5 w-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8h10m0 0-4-4m4 4-4 4" /></svg>
                    </x-ui.button>
                    <p class="flex items-center justify-center gap-1.5 text-xs text-ink-faint">
                        <svg viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5 text-pass" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4L8 11.6l6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" /></svg>
                        @if ($this->isLiveCrif)
                            {{ __('Your real CRIF score, free — checking your own score does not lower it') }}
                        @else
                            {{ __('Demo mode — see a sample score instantly, no real bureau pull') }}
                        @endif
                    </p>
                </div>
            @elseif ($step === 'otp')
                <p class="font-display text-xl font-semibold text-ink">{{ __('Verify your number') }}</p>
                <div class="mt-4 flex flex-col gap-4">
                    <p class="text-sm text-ink-muted">
                        {!! __('We\'ve sent a :length-digit code by SMS to <span class="font-medium text-ink">+91 :number</span>. It may take a moment to arrive.', ['length' => e($this->otpLength), 'number' => e($this->maskedMobileNumber)]) !!}
                    </p>
                    <div>
                        <label for="otpCode" class="text-sm font-medium text-ink">{{ __('Enter the :length-digit code', ['length' => $this->otpLength]) }}</label>
                        <input
                            id="otpCode"
                            type="text"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            maxlength="{{ $this->otpLength }}"
                            wire:model="otpCode"
                            class="mt-1.5 w-full rounded-lg border border-line-strong bg-surface px-3.5 py-2.5 text-sm tracking-[0.3em] text-ink placeholder:text-ink-faint focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/30"
                        >
                        @error('otpCode') <p class="mt-1 text-xs text-warn">{{ $message }}</p> @enderror
                    </div>
                    {{-- Keyed on the challenge so a resend re-mounts the countdown from the new code's cooldown. --}}
                    <div
                        class="flex items-center gap-3"
                        wire:key="otp-resend-{{ $otpChallengeId }}"
                        x-data="otpResendCountdown(@js($resendAfterSeconds))"
                    >
                        <x-ui.button wire:click="verifyOtp" wire:loading.attr="disabled">{{ __('Verify & continue') }}</x-ui.button>
                        <x-ui.button wire:click="resendOtp" wire:loading.attr="disabled" ::disabled="resendIn > 0" variant="ghost" size="sm">
                            <span x-text="resendIn > 0 ? @js(__('Resend code in :time')).replace(':time', `${resendIn}s`) : @js(__('Resend code'))">{{ __('Resend code') }}</span>
                        </x-ui.button>
                    </div>
                </div>
            @elseif ($step === 'details')
                <p class="font-display text-xl font-semibold text-ink">{{ __('A few last details') }}</p>
                <div class="mt-4 flex flex-col gap-4">
                    <div>
                        <label for="fullName" class="text-sm font-medium text-ink">{{ __('Full name (as per PAN)') }}</label>
                        <input
                            id="fullName"
                            type="text"
                            wire:model="fullName"
                            x-on:input="formatPanNameInput($event)"
                            class="mt-1.5 w-full rounded-lg border border-line-strong bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-faint focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/30"
                        >
                        @error('fullName') <p class="mt-1 text-xs text-warn">{{ $message }}</p> @enderror
                    </div>
                    <div
                        x-data="{ raw: isoToDateInputDigits(@js($dateOfBirth)) }"
                        x-init="$refs.dateOfBirthDisplay.value = formatDateInput(raw)"
                    >
                        <label for="dateOfBirth" class="text-sm font-medium text-ink">{{ __('Date of birth') }}</label>
                        {{-- A masked dd/mm/yyyy text input rather than <input type="date"> — the
                             native picker's displayed format follows the browser/OS locale (e.g.
                             MM/DD/YYYY on a US-locale browser), which doesn't match what Indian
                             applicants expect. The hidden input carries the real ISO value the
                             `dateOfBirth` property and its `date`/`before:-18 years` rules expect;
                             dispatching 'input' on it is what tells Livewire's deferred wire:model
                             the value changed. --}}
                        <input
                            type="text"
                            inputmode="numeric"
                            autocomplete="off"
                            placeholder="{{ __('DD/MM/YYYY') }}"
                            maxlength="10"
                            id="dateOfBirth"
                            x-ref="dateOfBirthDisplay"
                            x-on:input="
                                raw = $el.value.replace(/[^0-9]/g, '').slice(0, 8);
                                $el.value = formatDateInput(raw);
                                $refs.dateOfBirthHidden.value = dateInputToIso(raw);
                                $refs.dateOfBirthHidden.dispatchEvent(new Event('input', { bubbles: true }));
                            "
                            class="mt-1.5 w-full rounded-lg border border-line-strong bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-faint focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/30"
                        >
                        <input type="hidden" x-ref="dateOfBirthHidden" wire:model="dateOfBirth">
                        @error('dateOfBirth') <p class="mt-1 text-xs text-warn">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="panNumber" class="text-sm font-medium text-ink">{{ __('PAN number') }}</label>
                        <input
                            id="panNumber"
                            type="text"
                            maxlength="10"
                            placeholder="ABCDE1234F"
                            wire:model="panNumber"
                            style="text-transform: uppercase"
                            class="mt-1.5 w-full rounded-lg border border-line-strong bg-surface px-3.5 py-2.5 text-sm tracking-widest text-ink placeholder:text-ink-faint focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/30"
                        >
                        @error('panNumber') <p class="mt-1 text-xs text-warn">{{ $message }}</p> @enderror
                    </div>
                    <label class="flex items-start gap-2.5 text-sm text-ink-muted">
                        <input type="checkbox" wire:model="consent" class="mt-0.5 rounded border-line-strong text-accent focus:ring-accent/30">
                        <span>
                            {!! __('By submitting this form, you have read and agree to the <a href=":credit_report_terms_url" target="_blank" rel="noopener" class="text-accent underline">Credit Report Terms of Use</a>, <a href=":terms_url" target="_blank" rel="noopener" class="text-accent underline">Terms of Use</a> &amp; <a href=":privacy_policy_url" target="_blank" rel="noopener" class="text-accent underline">Privacy Policy</a>.', [
                                'credit_report_terms_url' => e(route('credit-report-terms')),
                                'terms_url' => e(route('terms')),
                                'privacy_policy_url' => e(route('privacy-policy')),
                            ]) !!}
                        </span>
                    </label>
                    @error('consent') <p class="text-xs text-warn">{{ $message }}</p> @enderror
                    <x-ui.button wire:click="submitDetails" wire:loading.attr="disabled" class="w-full justify-center">
                        {{ __('Check my score') }}
                    </x-ui.button>
                </div>
            @else
                <div class="flex flex-col items-center gap-5 py-2 text-center">
                    @if ($resultKind === 'live')
                        <x-ui.badge tone="pass">{{ __('Live score from CRIF High Mark') }}</x-ui.badge>
                        <div>
                            <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">{{ __('Your :bureau score', ['bureau' => $this->bureauLabel]) }}</p>
                            <p class="mt-1 font-display text-5xl font-semibold text-accent">{{ $resultScore }}</p>
                        </div>
                        <p class="max-w-sm text-sm text-ink-muted">
                            {{ __('CRIF scores range from 300 to 900 — the higher, the better your chances of a loan approval at a good rate. Checking your own score does not lower it.') }}
                        </p>
                    @elseif ($resultKind === 'no_score')
                        <x-ui.badge tone="muted">{{ __('No score yet') }}</x-ui.badge>
                        <p class="max-w-sm text-sm text-ink-muted">
                            {{ __('CRIF found your record but has no score for you yet. This usually means there is not enough credit history — a score appears a few months after your first loan or credit card.') }}
                        </p>
                    @elseif ($resultKind === 'pending')
                        <x-ui.badge tone="warn">{{ __('Still checking') }}</x-ui.badge>
                        <p class="max-w-sm text-sm text-ink-muted">
                            {{ __('We could not confirm your score with CRIF just now. Please try again in an hour.') }}
                        </p>
                    @elseif ($resultKind === 'failed')
                        <x-ui.badge tone="warn">{{ __('Score not available') }}</x-ui.badge>
                        <p class="max-w-sm text-sm text-ink-muted">
                            @if (in_array($resultError, [CrifErrorCode::NoRecordFound->value, CrifErrorCode::InvalidRequest->value, CrifErrorCode::ProviderRejected->value], true))
                                {{ __('CRIF could not find a credit record matching these details. Check that your name and PAN are exactly as on your PAN card, and that this mobile number is the one your bank or lender has.') }}
                            @else
                                {{ __('We could not fetch your CRIF score right now. Please try again a little later.') }}
                            @endif
                        </p>
                    @else
                        <x-ui.badge tone="warn">{{ __('Demo score — not your real credit report') }}</x-ui.badge>
                        <div>
                            <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">{{ __('Your :bureau score', ['bureau' => $this->bureauLabel]) }}</p>
                            <p class="mt-1 font-display text-5xl font-semibold text-accent">{{ $resultScore }}</p>
                        </div>
                        <p class="max-w-sm text-sm text-ink-muted">
                            {{ __('This is a sample score for demonstration purposes — :bureau isn\'t connected yet, so this figure isn\'t pulled from a real credit bureau and shouldn\'t be relied on.', ['bureau' => $this->bureauLabel]) }}
                        </p>
                    @endif
                    <x-ui.button wire:click="restart" variant="secondary" size="sm">{{ __('Check another number') }}</x-ui.button>
                </div>
            @endif
        </div>
    </div>
</x-ui.card>
