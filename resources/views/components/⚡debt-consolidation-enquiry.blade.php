<?php

use App\Modules\Customers\Actions\LogInCustomer;
use App\Modules\DebtConsolidation\Actions\SubmitDebtEnquiry;
use App\Modules\DebtConsolidation\Enums\ApplicantType;
use App\Modules\DebtConsolidation\Services\DebtConsolidationSettings;
use App\Modules\MobileOtp\Actions\RequestMobileOtp;
use App\Modules\MobileOtp\Actions\VerifyMobileOtp;
use App\Modules\MobileOtp\Enums\MobileOtpPurpose;
use App\Modules\MobileOtp\Enums\MobileOtpStatus;
use App\Modules\MobileOtp\Enums\MobileOtpVerificationResult;
use App\Modules\MobileOtp\Exceptions\MobileOtpThrottled;
use App\Modules\MobileOtp\Models\MobileOtpChallenge;
use App\Modules\MobileOtp\Services\MobileNumber;
use App\Support\Pages\DebtConsolidationPages;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/*
 * The short Debt Consolidation enquiry: who it is for, name, mobile and two
 * rough figures, then a one-time code. A verified number becomes an enquiry
 * through the site's one enquiry path (Lead Inbox, FynnOn LMS) and opens the
 * customer's case, which the detailed assessment continues. Nothing is
 * stored before the code is verified. Wrong codes are limited per challenge
 * and per IP here, since Livewire actions bypass route throttles.
 */
new class extends Component
{
    #[Locked]
    public string $source = 'hub';

    #[Locked]
    public string $heading = '';

    #[Locked]
    public string $subheading = '';

    /** @var 'details'|'otp'|'done' */
    #[Locked]
    public string $step = 'details';

    public string $applicantType = 'individual';

    public string $fullName = '';

    public string $mobileNumber = '';

    public string $email = '';

    public string $city = '';

    public ?string $totalOutstanding = null;

    public ?string $monthlyEmi = null;

    public bool $consent = false;

    public string $otpCode = '';

    #[Locked]
    public ?string $otpChallengeId = null;

    #[Locked]
    public ?string $challengedNumber = null;

    #[Locked]
    public int $resendAfterSeconds = 0;

    #[Locked]
    public ?string $reference = null;

    #[Locked]
    public ?string $sourceUrl = null;

    /** @var array<string, string> */
    #[Locked]
    public array $attribution = [];

    public function mount(string $source = 'hub', string $heading = '', string $subheading = ''): void
    {
        $this->source = $source;
        $this->heading = $heading;
        $this->subheading = $subheading;
        $this->sourceUrl = url()->current();
        $this->attribution = collect(['utm_source', 'utm_medium', 'utm_campaign'])
            ->mapWithKeys(fn (string $key): array => [$key => mb_substr((string) request()->query($key, ''), 0, 100)])
            ->filter()
            ->all();

        $types = $this->applicantTypes();
        $preferred = $source === 'business' ? ApplicantType::Business->value : ApplicantType::Individual->value;
        $this->applicantType = in_array($preferred, $types, true) ? $preferred : ($types[0] ?? ApplicantType::Individual->value);
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function applicantTypes(): array
    {
        return array_map(fn (ApplicantType $type): string => $type->value, DebtConsolidationSettings::availableApplicantTypes());
    }

    #[Computed]
    public function otpLength(): int
    {
        return MobileOtpChallenge::codeLength();
    }

    public function sendOtp(RequestMobileOtp $action): void
    {
        $this->mobileNumber = MobileNumber::normalize($this->mobileNumber);

        if ($this->applicantTypes() === []) {
            $this->addError('application', DebtConsolidationSettings::pausedMessage());

            return;
        }

        if ($this->step === 'details') {
            $this->validateDetails();
        }

        $number = $this->step === 'otp' ? (string) $this->challengedNumber : $this->mobileNumber;

        try {
            $challenge = $action->handle($number, MobileOtpPurpose::DebtConsolidation, request()->ip());
        } catch (MobileOtpThrottled $exception) {
            $this->addError($this->step === 'otp' ? 'otpCode' : 'mobileNumber', $exception->getMessage());

            return;
        }

        if ($challenge->status === MobileOtpStatus::Failed) {
            $this->addError($this->step === 'otp' ? 'otpCode' : 'mobileNumber', __('We could not send a verification code right now. Please try again shortly.'));

            return;
        }

        $this->otpChallengeId = $challenge->public_id;
        $this->challengedNumber = $number;
        $this->resendAfterSeconds = $challenge->resendAvailableInSeconds();
        $this->otpCode = '';
        $this->step = 'otp';
    }

    public function verifyOtp(VerifyMobileOtp $verify, SubmitDebtEnquiry $submit, LogInCustomer $logIn): void
    {
        if ($this->applicantTypes() === []) {
            $this->addError('application', DebtConsolidationSettings::pausedMessage());

            return;
        }

        $this->validate(['otpCode' => ['required', 'digits:'.$this->otpLength]]);

        $throttleKey = 'debt-consolidation-otp:'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, (int) config('customer_portal.login_max_attempts', 10))) {
            $this->addError('otpCode', __('Too many attempts. Please try again in :minutes minutes.', ['minutes' => (int) ceil(RateLimiter::availableIn($throttleKey) / 60)]));

            return;
        }

        $challenge = MobileOtpChallenge::findForVerification($this->otpChallengeId, $this->challengedNumber, MobileOtpPurpose::DebtConsolidation);
        $result = $challenge ? $verify->handle($challenge, $this->otpCode) : MobileOtpVerificationResult::NotFound;

        if (! $result->isVerified() || ! $challenge->consumeFor($this->challengedNumber, MobileOtpPurpose::DebtConsolidation)) {
            RateLimiter::hit($throttleKey, (int) config('customer_portal.login_decay_minutes', 15) * 60);

            $this->addError('otpCode', $result === MobileOtpVerificationResult::AttemptsExhausted
                ? $result->message()
                : __('That code is incorrect or has expired. You can resend a new one.'));

            return;
        }

        RateLimiter::clear($throttleKey);

        // The details are re-checked at the step that stores them: the browser holds
        // them between the two steps and could have changed them.
        $this->validateDetails();

        $application = $submit->handle((string) $this->challengedNumber, ApplicantType::from($this->applicantType), [
            'full_name' => trim($this->fullName),
            'email' => filled($this->email) ? trim($this->email) : null,
            'city' => filled($this->city) ? trim($this->city) : null,
            'declared_total_outstanding' => filled($this->totalOutstanding) ? (float) $this->totalOutstanding : null,
            'declared_monthly_emi' => filled($this->monthlyEmi) ? (float) $this->monthlyEmi : null,
            'source_url' => $this->sourceUrl,
            ...$this->attribution,
        ], request()->ip(), request()->userAgent());

        if ($application->customer !== null) {
            $logIn->handle($application->customer, 'otp');
        }

        $this->reference = $application->reference;
        $this->step = 'done';
    }

    private function validateDetails(): void
    {
        $this->validate([
            'applicantType' => ['required', Rule::in($this->applicantTypes())],
            'fullName' => ['required', 'string', 'min:2', 'max:150'],
            'mobileNumber' => ['required', 'regex:'.MobileNumber::PATTERN],
            'email' => ['nullable', 'email', 'max:191'],
            'city' => ['nullable', 'string', 'max:100'],
            'totalOutstanding' => ['nullable', 'numeric', 'min:0', 'max:10000000000'],
            'monthlyEmi' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'consent' => ['accepted'],
        ], [
            'consent.accepted' => __('Please agree to be contacted so an advisor can call you.'),
        ], [
            'applicantType' => __('who this is for'),
            'fullName' => __('name'),
            'mobileNumber' => __('mobile number'),
            'totalOutstanding' => __('total outstanding'),
            'monthlyEmi' => __('monthly EMIs'),
        ]);
    }

    public function startOver(): void
    {
        $this->reset('step', 'otpCode', 'otpChallengeId', 'challengedNumber', 'resendAfterSeconds');
    }

    #[Computed]
    public function assessmentUrl(): ?string
    {
        return DebtConsolidationPages::liveUrl('assessment');
    }
};
?>

<div class="rounded-2xl border border-line bg-surface p-6 shadow-sm ring-4 ring-accent/15">
    @error('application') <x-ui.alert tone="warn" class="mb-4">{{ $message }}</x-ui.alert> @enderror
    @if ($step === 'done')
        <div class="flex flex-col gap-4" role="status">
            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-pass-soft text-pass" aria-hidden="true">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7" /></svg>
            </span>
            <h2 class="font-display text-lg font-semibold text-ink">{{ __('Thank you — your enquiry is in') }}</h2>
            <p class="text-sm text-ink-muted">{{ __('Your reference is :reference. A FynnEdge advisor will call you shortly.', ['reference' => $reference]) }}</p>
            <p class="text-sm text-ink-muted">{{ __('Want a head start? Add your loans now for a free, detailed assessment — your details are saved to your profile.') }}</p>
            <div class="flex flex-wrap gap-3">
                @if ($this->assessmentUrl)
                    <x-ui.button tag="a" :href="$this->assessmentUrl">{{ __('Continue to detailed assessment') }}</x-ui.button>
                @endif
                <x-ui.button tag="a" :href="route('portal.dashboard')" variant="ghost">{{ __('Go to my profile') }}</x-ui.button>
            </div>
        </div>
    @elseif ($step === 'otp')
        <form wire:submit="verifyOtp" class="flex flex-col gap-4">
            <h2 class="font-display text-lg font-semibold text-ink">{{ __('Verify your mobile number') }}</h2>
            <p class="text-sm text-ink-muted">
                {!! __('We have sent a :length-digit code to <span class="font-medium text-ink">+91 :number</span>.', ['length' => e($this->otpLength), 'number' => e(MobileNumber::mask((string) $challengedNumber))]) !!}
            </p>
            <div>
                <label for="debtEnquiryOtp" class="text-sm font-medium text-ink">{{ __('Enter the :length-digit code', ['length' => $this->otpLength]) }}</label>
                <input id="debtEnquiryOtp" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="{{ $this->otpLength }}" wire:model="otpCode"
                    class="mt-1.5 w-full rounded-lg border border-line-strong bg-surface px-3.5 py-2.5 text-sm tracking-[0.3em] text-ink focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/30">
                @error('otpCode') <p class="mt-1 text-xs text-warn">{{ $message }}</p> @enderror
            </div>
            <div class="flex flex-wrap items-center gap-3" wire:key="debt-resend-{{ $otpChallengeId }}" x-data="otpResendCountdown(@js($resendAfterSeconds))">
                <x-ui.button type="submit" wire:loading.attr="disabled">{{ __('Verify & submit') }}</x-ui.button>
                <x-ui.button type="button" wire:click="sendOtp" wire:loading.attr="disabled" ::disabled="resendIn > 0" variant="ghost" size="sm">
                    <span x-text="resendIn > 0 ? @js(__('Resend code in :time')).replace(':time', `${resendIn}s`) : @js(__('Resend code'))">{{ __('Resend code') }}</span>
                </x-ui.button>
                <x-ui.button type="button" wire:click="startOver" variant="ghost" size="sm">{{ __('Change details') }}</x-ui.button>
            </div>
        </form>
    @else
        <form wire:submit="sendOtp" class="flex flex-col gap-4">
            <div>
                <h2 class="font-display text-lg font-semibold text-ink">{{ $heading ?: __('Talk to a debt consolidation advisor') }}</h2>
                @if ($subheading)
                    <p class="mt-1 text-sm text-ink-muted">{{ $subheading }}</p>
                @endif
            </div>

            @if (count($this->applicantTypes) > 1)
                <fieldset>
                    <legend class="text-sm font-medium text-ink">{{ __('This is for') }}</legend>
                    <div class="mt-1.5 grid grid-cols-2 gap-2">
                        @foreach ($this->applicantTypes as $type)
                            <label @class(['flex cursor-pointer items-center justify-center rounded-lg border px-3 py-2 text-sm font-medium transition-colors', 'border-accent bg-accent-soft text-accent' => $applicantType === $type, 'border-line-strong text-ink-muted' => $applicantType !== $type])>
                                <input type="radio" class="sr-only" value="{{ $type }}" wire:model.live="applicantType">
                                {{ $type === 'business' ? __('My business') : __('Me / my family') }}
                            </label>
                        @endforeach
                    </div>
                    @error('applicantType') <p class="mt-1 text-xs text-warn">{{ $message }}</p> @enderror
                </fieldset>
            @endif

            @foreach ([
                ['model' => 'fullName', 'label' => $applicantType === 'business' ? __('Your name (owner or director)') : __('Full name'), 'type' => 'text', 'autocomplete' => 'name', 'inputmode' => null, 'required' => true],
                ['model' => 'mobileNumber', 'label' => __('Mobile number'), 'type' => 'tel', 'autocomplete' => 'tel-national', 'inputmode' => 'numeric', 'required' => true],
                ['model' => 'city', 'label' => __('City (optional)'), 'type' => 'text', 'autocomplete' => 'address-level2', 'inputmode' => null, 'required' => false],
                ['model' => 'totalOutstanding', 'label' => __('Total outstanding on all loans, roughly (₹, optional)'), 'type' => 'text', 'autocomplete' => 'off', 'inputmode' => 'numeric', 'required' => false],
                ['model' => 'monthlyEmi', 'label' => __('Total monthly EMIs, roughly (₹, optional)'), 'type' => 'text', 'autocomplete' => 'off', 'inputmode' => 'numeric', 'required' => false],
            ] as $field)
                <div>
                    <label for="debtEnquiry-{{ $field['model'] }}" class="text-sm font-medium text-ink">{{ $field['label'] }}</label>
                    <input id="debtEnquiry-{{ $field['model'] }}" type="{{ $field['type'] }}" autocomplete="{{ $field['autocomplete'] }}" @if ($field['inputmode']) inputmode="{{ $field['inputmode'] }}" @endif @if ($field['required']) required @endif
                        @if ($field['model'] === 'mobileNumber') maxlength="10" placeholder="{{ __('10-digit mobile number') }}" @endif
                        wire:model="{{ $field['model'] }}"
                        class="mt-1.5 w-full rounded-lg border border-line-strong bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-faint focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/30">
                    @error($field['model']) <p class="mt-1 text-xs text-warn">{{ $message }}</p> @enderror
                </div>
            @endforeach

            <label class="flex items-start gap-2 text-xs leading-relaxed text-ink-muted">
                <input type="checkbox" wire:model="consent" class="mt-0.5 h-4 w-4 rounded border-line-strong text-accent focus:ring-accent">
                <span>{{ \App\Modules\DebtConsolidation\Services\DebtConsolidationSettings::text('consent_contact') }}</span>
            </label>
            @error('consent') <p class="-mt-2 text-xs text-warn">{{ $message }}</p> @enderror

            <x-ui.button type="submit" wire:loading.attr="disabled" class="w-full justify-center">{{ __('Send OTP') }}</x-ui.button>
            <p class="text-center text-xs text-ink-faint">{{ __('Free and without obligation. We never share your details with a lender without your consent.') }}</p>
        </form>
    @endif
</div>
