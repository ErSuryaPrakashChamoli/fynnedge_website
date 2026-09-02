<?php

use App\Modules\CreditScore\Actions\RequestMobileOtp;
use App\Modules\CreditScore\Actions\SubmitCreditScoreCheck;
use App\Modules\CreditScore\Actions\VerifyMobileOtp;
use App\Modules\CreditScore\Enums\BureauName;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use App\Modules\CreditScore\Models\MobileOtpChallenge;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $bureau;

    /** @var 'mobile'|'otp'|'details'|'result' */
    public string $step = 'mobile';

    public string $mobileNumber = '';

    public string $otpCode = '';

    public ?int $otpChallengeId = null;

    /**
     * Held only in the component's own memory for the current session — never
     * persisted in plaintext (MobileOtpChallenge stores a hash). Shown directly
     * on screen because there's no SMS gateway configured yet; this is clearly
     * labelled "demo mode" in the view rather than silently pretending an SMS
     * went out.
     */
    public ?string $demoOtpCode = null;

    public string $fullName = '';

    public string $dateOfBirth = '';

    public string $panNumber = '';

    public bool $consent = false;

    public ?int $resultScore = null;

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

    public function sendOtp(RequestMobileOtp $action): void
    {
        $this->validate([
            'mobileNumber' => ['required', 'regex:/^[6-9]\d{9}$/'],
        ], [], ['mobileNumber' => 'mobile number']);

        ['challenge' => $challenge, 'code' => $code] = $action->handle($this->mobileNumber, request()->ip());

        $this->otpChallengeId = $challenge->id;
        $this->demoOtpCode = $code;
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
            'otpCode' => ['required', 'digits:6'],
        ]);

        $challenge = $this->otpChallengeId ? MobileOtpChallenge::find($this->otpChallengeId) : null;

        if (! $challenge || ! $action->handle($challenge, $this->otpCode)) {
            $this->addError('otpCode', 'That code is incorrect or has expired. You can resend a new one.');

            return;
        }

        $this->step = 'details';
    }

    public function updatedPanNumber(string $value): void
    {
        $this->panNumber = strtoupper($value);
    }

    public function submitDetails(SubmitCreditScoreCheck $action): void
    {
        $validated = $this->validate([
            'fullName' => ['required', 'string', 'max:120'],
            'dateOfBirth' => ['required', 'date', 'before:-18 years'],
            'panNumber' => ['required', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/'],
            'consent' => ['accepted'],
        ], [
            'dateOfBirth.before' => 'You must be at least 18 years old.',
            'panNumber.regex' => 'Enter a valid PAN (e.g. ABCDE1234F).',
            'consent.accepted' => 'Please provide consent to continue.',
        ]);

        $check = $action->handle(
            [
                'full_name' => $validated['fullName'],
                'date_of_birth' => $validated['dateOfBirth'],
                'pan_number' => $validated['panNumber'],
            ],
            BureauName::from($this->bureau),
            $this->mobileNumber,
            request()->ip(),
        );

        $this->resultScore = $check->score;
        $this->step = 'result';
    }

    public function restart(): void
    {
        $this->reset([
            'step', 'mobileNumber', 'otpCode', 'otpChallengeId', 'demoOtpCode',
            'fullName', 'dateOfBirth', 'panNumber', 'consent', 'resultScore',
        ]);
    }
};
?>

<x-ui.card :padded="false" class="overflow-hidden">
    @if ($step === 'mobile')
        <div class="flex items-center gap-2.5 bg-accent-soft px-6 py-3 text-sm text-accent-strong">
            <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 shrink-0" aria-hidden="true"><path d="M10 2a6 6 0 0 0-6 6c0 4-2 5.5-2 5.5h16S16 12 16 8a6 6 0 0 0-6-6ZM8.5 16a1.5 1.5 0 0 0 3 0h-3Z" /></svg>
            Check your free {{ $this->bureauLabel }} score in under 2 minutes
        </div>
    @endif

    <div class="p-6 sm:p-7">
        <x-ui.stepper :steps="['Mobile number', 'Verify OTP', 'Your details', 'Your score']" :current="$this->stepIndex" />

        <div class="mt-7">
            @if ($step === 'mobile')
                <p class="font-display text-xl font-semibold text-ink">Let's get started</p>
                <div class="mt-4 flex flex-col gap-4">
                    <div>
                        <label for="mobileNumber" class="text-sm font-medium text-ink">Mobile number</label>
                        <input
                            id="mobileNumber"
                            type="tel"
                            inputmode="numeric"
                            maxlength="10"
                            placeholder="10-digit mobile number"
                            wire:model="mobileNumber"
                            class="mt-1.5 w-full rounded-lg border border-line-strong bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-faint focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/30"
                        >
                        @error('mobileNumber')
                            <p class="mt-1 text-xs text-warn">{{ $message }}</p>
                        @else
                            <p class="mt-1.5 text-xs text-ink-faint">You'll receive an OTP on this number.</p>
                        @enderror
                    </div>
                    <p class="text-xs text-ink-faint">
                        By continuing, you agree to FynnEdge's <a href="{{ route('terms') }}" class="underline hover:text-ink">Terms</a> and <a href="{{ route('privacy-policy') }}" class="underline hover:text-ink">Privacy Policy</a>.
                    </p>
                    <x-ui.button wire:click="sendOtp" wire:loading.attr="disabled" class="w-full justify-center">
                        Get Free {{ $this->bureauLabel }} Score
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" class="h-3.5 w-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8h10m0 0-4-4m4 4-4 4" /></svg>
                    </x-ui.button>
                    <p class="flex items-center justify-center gap-1.5 text-xs text-ink-faint">
                        <svg viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5 text-pass" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4L8 11.6l6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" /></svg>
                        Demo mode — see a sample score instantly, no real bureau pull
                    </p>
                </div>
            @elseif ($step === 'otp')
                <p class="font-display text-xl font-semibold text-ink">Verify your number</p>
                <div class="mt-4 flex flex-col gap-4">
                    <x-ui.alert tone="accent" title="Demo mode">
                        There's no SMS gateway connected yet, so your one-time code is shown right here instead of being texted to you: <span class="font-mono text-base font-semibold text-ink">{{ $demoOtpCode }}</span>
                    </x-ui.alert>
                    <div>
                        <label for="otpCode" class="text-sm font-medium text-ink">Enter the 6-digit code</label>
                        <input
                            id="otpCode"
                            type="text"
                            inputmode="numeric"
                            maxlength="6"
                            wire:model="otpCode"
                            class="mt-1.5 w-full rounded-lg border border-line-strong bg-surface px-3.5 py-2.5 text-sm tracking-[0.3em] text-ink placeholder:text-ink-faint focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/30"
                        >
                        @error('otpCode') <p class="mt-1 text-xs text-warn">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex items-center gap-3">
                        <x-ui.button wire:click="verifyOtp" wire:loading.attr="disabled">Verify &amp; continue</x-ui.button>
                        <x-ui.button wire:click="resendOtp" wire:loading.attr="disabled" variant="ghost" size="sm">Resend code</x-ui.button>
                    </div>
                </div>
            @elseif ($step === 'details')
                <p class="font-display text-xl font-semibold text-ink">A few last details</p>
                <div class="mt-4 flex flex-col gap-4">
                    <div>
                        <label for="fullName" class="text-sm font-medium text-ink">Full name (as per PAN)</label>
                        <input
                            id="fullName"
                            type="text"
                            wire:model="fullName"
                            class="mt-1.5 w-full rounded-lg border border-line-strong bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-faint focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/30"
                        >
                        @error('fullName') <p class="mt-1 text-xs text-warn">{{ $message }}</p> @enderror
                    </div>
                    <div
                        x-data="{ raw: isoToDateInputDigits(@js($dateOfBirth)) }"
                        x-init="$refs.dateOfBirthDisplay.value = formatDateInput(raw)"
                    >
                        <label for="dateOfBirth" class="text-sm font-medium text-ink">Date of birth</label>
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
                            placeholder="DD/MM/YYYY"
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
                        <label for="panNumber" class="text-sm font-medium text-ink">PAN number</label>
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
                            By submitting this form, you have read and agree to the
                            <a href="{{ route('credit-report-terms') }}" target="_blank" rel="noopener" class="text-accent underline">Credit Report Terms of Use</a>,
                            <a href="{{ route('terms') }}" target="_blank" rel="noopener" class="text-accent underline">Terms of Use</a>
                            &amp;
                            <a href="{{ route('privacy-policy') }}" target="_blank" rel="noopener" class="text-accent underline">Privacy Policy</a>.
                        </span>
                    </label>
                    @error('consent') <p class="text-xs text-warn">{{ $message }}</p> @enderror
                    <x-ui.button wire:click="submitDetails" wire:loading.attr="disabled" class="w-full justify-center">
                        Check my score
                    </x-ui.button>
                </div>
            @else
                <div class="flex flex-col items-center gap-5 py-2 text-center">
                    <x-ui.badge tone="warn">Demo score — not your real credit report</x-ui.badge>
                    <div>
                        <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">Your {{ $this->bureauLabel }} score</p>
                        <p class="mt-1 font-display text-5xl font-semibold text-accent">{{ $resultScore }}</p>
                    </div>
                    <p class="max-w-sm text-sm text-ink-muted">
                        This is a sample score for demonstration purposes — {{ $this->bureauLabel }} isn't connected yet, so this figure isn't pulled from a real credit bureau and shouldn't be relied on.
                    </p>
                    <x-ui.button wire:click="restart" variant="secondary" size="sm">Check another number</x-ui.button>
                </div>
            @endif
        </div>
    </div>
</x-ui.card>
