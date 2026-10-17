<?php

use App\Modules\Cibil\Services\CibilSettings;
use App\Modules\CreditBureau\Enums\CreditCheckStatus;
use App\Modules\CreditBureau\Enums\CrifErrorCode;
use App\Modules\CreditBureau\Services\CrifSettings;
use App\Modules\CreditBureau\Services\PanName;
use App\Modules\CreditScore\Actions\SubmitCreditScoreCheck;
use App\Modules\CreditScore\Enums\BureauName;
use App\Modules\CreditScore\Enums\ScoreBand;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use App\Modules\CreditScore\Services\CreditScoreAdvice;
use App\Modules\Wallet\DataTransferObjects\BillingQuote;
use App\Modules\Wallet\Enums\CheckBilling;
use App\Modules\Wallet\Exceptions\InsufficientWalletBalance;
use App\Modules\Wallet\Models\WalletTransaction;
use App\Modules\Wallet\Services\CreditCheckBilling;
use App\Modules\Wallet\Services\Wallet;
use App\Modules\Customers\Actions\LogInCustomer;
use App\Modules\Customers\Actions\ResolveCustomerAccount;
use App\Modules\Customers\Models\Customer;
use App\Modules\Equifax\Enums\Gender;
use App\Modules\Equifax\Services\EquifaxSettings;
use App\Modules\Experian\Services\ExperianSettings;
use App\Modules\MobileOtp\Actions\RequestMobileOtp;
use App\Modules\MobileOtp\Actions\VerifyMobileOtp;
use App\Modules\MobileOtp\Enums\MobileOtpPurpose;
use App\Modules\MobileOtp\Enums\MobileOtpStatus;
use App\Modules\MobileOtp\Enums\MobileOtpVerificationResult;
use App\Modules\MobileOtp\Exceptions\MobileOtpThrottled;
use App\Modules\MobileOtp\Models\MobileOtpChallenge;
use App\Modules\MobileOtp\Services\MobileNumber;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    public string $bureau;

    /**
     * 'public' on /credit-score/{bureau}; 'portal' when a signed-in customer
     * refreshes a score from /my-profile (only the result step's links differ).
     */
    #[Locked]
    public string $context = 'public';

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

    /**
     * The name as on the PAN card, asked the way the page's bureau API takes it
     * (BureauName::takesSplitName()): one box for CIBIL, Experian and Equifax,
     * whose APIs take a single `name`…
     */
    public string $fullName = '';

    /**
     * …and three boxes on the CRIF page: Surepass's CRIF API takes first_name
     * and last_name apart (a middle name travels inside first_name), and
     * splitting one box guessed wrong on two-word surnames like "Sen Gupta".
     */
    public string $firstName = '';

    public string $middleName = '';

    public string $lastName = '';

    /**
     * Asked only on the live Equifax page: Surepass's Equifax API requires the
     * gender and accepts exactly "male" or "female".
     */
    public string $gender = '';

    public string $dateOfBirth = '';

    public string $panNumber = '';

    public bool $consent = false;

    public ?int $resultScore = null;

    /**
     * What the result step shows: 'demo' (sample score), 'live' (a real bureau
     * score), 'no_score' (the bureau answered but holds no score), 'pending' (outcome
     * not confirmed yet) or 'failed'. Locked: the browser must never be able to
     * dress a demo score up as a real one.
     */
    #[Locked]
    public ?string $resultKind = null;

    /** The error code behind a failed live check, for the visitor's message. */
    #[Locked]
    public ?string $resultError = null;

    /**
     * The headline figures of the live report behind the score (see
     * CrifReportDocument::insights()), for the result's factors and advice.
     * Null for a demo score or when no report was read.
     *
     * @var array<string, int|null>|null
     */
    #[Locked]
    public ?array $resultInsights = null;

    /**
     * The check just run, by public id, when it was saved to a portal account —
     * for the "View report" link. Never a bureau request id or report data.
     */
    #[Locked]
    public ?string $resultCheckId = null;

    /** 'signed_in' once the check's account is open in this browser, 'disabled' when staff switched it off. */
    #[Locked]
    public ?string $accountStatus = null;

    /**
     * Paid refreshes (CreditCheckBilling): what this check would cost, shown
     * on the details step once the number is verified…
     */
    #[Locked]
    public ?string $billingNotice = null;

    /**
     * …the amounts when the wallet cannot cover it (the check does not run and
     * the verification is not spent, so the customer can top up and submit
     * again)…
     *
     * @var array{price: string, gst: string, total: string, balance: string, free_again: ?string}|null
     */
    #[Locked]
    public ?array $paymentRequired = null;

    /** …and what was charged, on the result. */
    #[Locked]
    public ?string $billingSummary = null;

    /**
     * Accepts a plain string (from the `:bureau="$bureau->value"` the controller
     * passes in) rather than requiring a typed BureauName — Livewire's test
     * harness assigns initial params to typed public properties directly, before
     * mount() runs, so a strict enum type here would reject the raw string it's
     * given. Mirrors the same accepts-string pattern used by the EMI calculator.
     */
    public function mount(string $bureau, string $context = 'public'): void
    {
        $this->bureau = (BureauName::tryFrom($bureau) ?? BureauName::Cibil)->value;
        $this->context = $context === 'portal' ? 'portal' : 'public';
        $this->mobileNumber = $this->lockedToAccount ? (string) $this->signedInCustomer->verified_mobile : '';
    }

    /** The signed-in portal customer, if any. */
    #[Computed]
    public function signedInCustomer(): ?Customer
    {
        $customer = Auth::guard(LogInCustomer::GUARD)->user();

        return $customer instanceof Customer && $customer->hasPortalAccess() ? $customer : null;
    }

    /**
     * A refresh from the portal always goes to the customer's own registered
     * mobile. The public page stays open to any number — a second person on a
     * shared browser checks their own — and nothing of the signed-in
     * customer's is shown until an OTP proves the number is theirs.
     */
    #[Computed]
    public function lockedToAccount(): bool
    {
        return $this->context === 'portal' && $this->signedInCustomer !== null;
    }

    /**
     * The customer's own details, once their OTP is verified: from their last
     * check on this bureau's page, else from their profile. They can still
     * correct anything before submitting.
     */
    private function prefillFrom(Customer $customer): void
    {
        $latest = $customer->creditScoreChecks()->where('bureau', $this->bureau)->latest('id')->first();

        $this->fullName = (string) ($latest?->full_name ?? $customer->full_name ?? '');
        $this->firstName = (string) ($latest?->first_name ?? '');
        $this->middleName = (string) ($latest?->middle_name ?? '');
        $this->lastName = (string) ($latest?->last_name ?? '');
        $this->dateOfBirth = (string) (($latest?->date_of_birth ?? $customer->date_of_birth)?->toDateString() ?? '');
        $this->gender = (string) ($latest?->gender ?? $customer->gender?->value ?? '');
        $this->panNumber = (string) ($latest?->pan_number ?? $customer->pan() ?? '');
    }

    #[Computed]
    public function bureauLabel(): string
    {
        return BureauName::from($this->bureau)->getLabel();
    }

    /**
     * Real scores (a paid Surepass call per new check) only while the page's
     * own Settings switch is on (CRIF, Equifax, Experian, CIBIL Settings) and
     * the server has the token. The CIBIL page has no CIBIL API behind it: its
     * real scores come from a stand-in bureau (see standInSource()).
     */
    #[Computed]
    public function isLive(): bool
    {
        return match ($this->bureau) {
            BureauName::Crif->value => CrifSettings::publicScoreLive(),
            BureauName::Equifax->value => EquifaxSettings::publicScoreLive(),
            BureauName::Experian->value => ExperianSettings::publicScoreLive(),
            BureauName::Cibil->value => CibilSettings::publicScoreLive(),
        };
    }

    /**
     * The bureau whose real score this page shows when it is not the page's
     * own — Experian on the CIBIL page while no CIBIL API exists. The page then
     * says so at every step: a visitor is never told an Experian score is
     * their CIBIL score.
     */
    #[Computed]
    public function standInSource(): ?string
    {
        return $this->bureau === BureauName::Cibil->value && $this->isLive && CibilSettings::isStandIn() ? CibilSettings::sourceLabel() : null;
    }

    /** The bureau named on a live result: the stand-in when there is one. */
    #[Computed]
    public function scoreBureau(): string
    {
        return $this->standInSource ?? $this->bureauLabel;
    }

    #[Computed]
    public function takesSplitName(): bool
    {
        return BureauName::from($this->bureau)->takesSplitName();
    }

    /** The band, report factors and pointers shown with the result. */
    #[Computed]
    public function advice(): CreditScoreAdvice
    {
        $score = in_array($this->resultKind, ['live', 'demo'], true) ? $this->resultScore : null;

        return new CreditScoreAdvice($score, $this->resultKind === 'live' ? $this->resultInsights : null, $this->scoreBureau);
    }

    /**
     * Asked only where the live bureau requires it: Equifax, and CIBIL when the
     * CIBIL bureau itself (not the Experian stand-in) answers the page.
     */
    #[Computed]
    public function needsGender(): bool
    {
        $requiresGender = $this->bureau === BureauName::Equifax->value
            || ($this->bureau === BureauName::Cibil->value && ! CibilSettings::isStandIn());

        return $requiresGender && $this->isLive;
    }

    /** Who a live score comes from, as the visitor knows it. */
    #[Computed]
    public function liveSource(): string
    {
        return $this->standInSource ?? ($this->bureau === BureauName::Crif->value ? 'CRIF High Mark' : $this->bureauLabel);
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
        $this->mobileNumber = MobileNumber::normalize($this->lockedToAccount ? $this->signedInCustomer->verified_mobile : $this->mobileNumber);

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

        if ($this->lockedToAccount) {
            $this->mobileNumber = (string) $this->signedInCustomer->verified_mobile;
        }

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
        $this->billingNotice = $this->previewBilling();

        if ($this->signedInCustomer?->verified_mobile === $this->verifiedMobileNumber) {
            $this->prefillFrom($this->signedInCustomer);
        }
    }

    /** "SURYA" → "Surya", "sen GUPTA" → "Sen Gupta", whichever case each name was typed in. */
    public function updated(string $property, mixed $value): void
    {
        if (in_array($property, ['fullName', 'firstName', 'middleName', 'lastName'], true)) {
            $this->{$property} = PanName::normalize($value);
        }
    }

    public function updatedPanNumber(string $value): void
    {
        $this->panNumber = strtoupper($value);
    }

    public function submitDetails(SubmitCreditScoreCheck $action, ResolveCustomerAccount $accounts, LogInCustomer $logIn, CreditCheckBilling $billing): void
    {
        if ($this->step !== 'details' || $this->verifiedMobileNumber === null) {
            $this->restart();

            return;
        }

        $nameRules = $this->takesSplitName
            ? [
                'firstName' => ['required', 'string', 'max:100'],
                'middleName' => ['nullable', 'string', 'max:100'],
                'lastName' => ['required', 'string', 'max:100'],
            ]
            : ['fullName' => ['required', 'string', 'max:150']];

        $validated = $this->validate([
            ...$nameRules,
            'dateOfBirth' => ['required', 'date', 'before:-18 years'],
            'panNumber' => ['required', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/'],
            'gender' => [$this->needsGender ? 'required' : 'nullable', Rule::enum(Gender::class)],
            'consent' => ['accepted'],
        ], [
            'gender.required' => __('Please choose your gender — :bureau needs it to find your credit record.', ['bureau' => $this->bureauLabel]),
            'dateOfBirth.before' => __('You must be at least 18 years old.'),
            'panNumber.regex' => __('Enter a valid PAN (e.g. ABCDE1234F).'),
            'consent.accepted' => __('Please provide consent to continue.'),
        ]);

        $name = $this->takesSplitName
            ? [
                'full_name' => Str::squish(implode(' ', [$validated['firstName'], $validated['middleName'] ?? '', $validated['lastName']])),
                'first_name' => $validated['firstName'],
                'middle_name' => $validated['middleName'] ?? null,
                'last_name' => $validated['lastName'],
            ]
            : ['full_name' => $validated['fullName'], 'first_name' => null, 'middle_name' => null, 'last_name' => null];

        $details = [
            ...$name,
            'date_of_birth' => $validated['dateOfBirth'],
            'pan_number' => $validated['panNumber'],
            'gender' => $this->needsGender ? $validated['gender'] : null,
        ];
        $bureau = BureauName::from($this->bureau);
        $customer = $this->accountFor($this->verifiedMobileNumber, $accounts);
        $this->paymentRequired = null;

        if ($customer !== null && CreditCheckBilling::enabled()) {
            try {
                // One billed check per customer at a time: two tabs can never
                // both spend the free check or the same wallet balance.
                $check = Cache::lock('credit-check-billing:'.$customer->getKey(), 120)
                    ->block(15, fn (): ?CreditScoreCheck => $this->runBilled($action, $billing, $logIn, $customer, $bureau, $details));
            } catch (LockTimeoutException) {
                $this->addError('consent', __('Another credit check for your profile is still running. Please wait a moment and try again.'));

                return;
            }

            if ($check === null) {
                return;
            }
        } elseif (CreditCheckBilling::enabled() && SubmitCreditScoreCheck::isLive($bureau)) {
            // A paid pull that cannot be billed to anyone never runs.
            $this->addError('consent', __('We could not start your check right now. Please try again in a few minutes.'));

            return;
        } else {
            if (! $this->spendVerification()) {
                return;
            }

            $check = $action->handle($details, $bureau, $this->verifiedMobileNumber, request()->ip(), $customer);
        }

        if ($customer !== null) {
            if ($check->isSuccessful()) {
                $customer->fillBlankProfileDetails($details);
            }

            $this->resultCheckId = $check->exists && $check->customer_id === $customer->id ? $check->public_id : null;
            $this->accountStatus = $this->signedInCustomer?->is($customer) || $logIn->handle($customer, 'credit_score_check') ? 'signed_in' : 'disabled';
            unset($this->signedInCustomer, $this->lockedToAccount);
        }

        $this->resultScore = $check->score;
        $this->resultInsights = $check->report_insights;
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

    /**
     * The verification is spent on exactly one check — re-submitting, or a
     * second tab replaying the same component state, has nothing left to spend.
     */
    private function spendVerification(): bool
    {
        $challenge = MobileOtpChallenge::findForVerification($this->otpChallengeId, $this->verifiedMobileNumber, MobileOtpPurpose::CreditScoreCheck);

        if (! $challenge?->consumeFor($this->verifiedMobileNumber, MobileOtpPurpose::CreditScoreCheck)) {
            $this->restart();
            $this->addError('mobileNumber', __('Your verification has expired. Please verify your mobile number again.'));

            return false;
        }

        return true;
    }

    /**
     * A check with billing on: priced, the price held, the verification spent,
     * the bureau asked, then the hold captured (new answer) or released.
     * Null when it did not run (wallet short, or verification expired).
     *
     * @param  array<string, mixed>  $details
     */
    private function runBilled(SubmitCreditScoreCheck $action, CreditCheckBilling $billing, LogInCustomer $logIn, Customer $customer, BureauName $bureau, array $details): ?CreditScoreCheck
    {
        $quote = $billing->quote($customer, $bureau, $details['pan_number'], $this->verifiedMobileNumber);

        try {
            $hold = $quote->needsTopUp() ? throw new InsufficientWalletBalance($quote->balancePaise, $quote->price->totalPaise()) : $billing->reserve($customer, $bureau, $quote);
        } catch (InsufficientWalletBalance) {
            $this->askForPayment($quote, $customer, $logIn);

            return null;
        }

        if (! $this->spendVerification()) {
            $hold !== null && app(Wallet::class)->release($hold);

            return null;
        }

        try {
            $check = $action->handle($details, $bureau, $this->verifiedMobileNumber, request()->ip(), $customer);
        } catch (\Throwable $exception) {
            $hold !== null && app(Wallet::class)->release($hold);

            throw $exception;
        }

        $billing->settle($quote, $hold, $check);
        $this->billingSummary = $this->billingSummaryFor($quote, $hold, $check);

        return $check;
    }

    /**
     * Shows what the refresh costs and opens the customer's profile in this
     * browser, so "Add money" lands on their own wallet.
     */
    private function askForPayment(BillingQuote $quote, Customer $customer, LogInCustomer $logIn): void
    {
        $this->paymentRequired = [
            'price' => Wallet::format($quote->price->taxablePaise),
            'gst' => Wallet::format($quote->price->gstPaise),
            'total' => Wallet::format($quote->price->totalPaise()),
            'balance' => Wallet::format($quote->balancePaise),
            'free_again' => $quote->freeAgainAt?->translatedFormat('d M Y'),
        ];

        if (! $this->signedInCustomer?->is($customer)) {
            $logIn->handle($customer, 'credit_score_check');
            unset($this->signedInCustomer, $this->lockedToAccount);
        }
    }

    private function billingSummaryFor(BillingQuote $quote, ?WalletTransaction $hold, CreditScoreCheck $check): ?string
    {
        $check->refresh();

        return match (true) {
            $check->billing === CheckBilling::Wallet => __(':amount (including GST) was paid from your wallet.', ['amount' => Wallet::format(abs($hold->amount_paise))]),
            $check->billing === CheckBilling::Free => __('This was your free check. Your next free check is due on :date.', ['date' => $check->completed_at->copy()->addMonths(CreditCheckBilling::freeEveryMonths())->translatedFormat('d M Y')]),
            $check->billing === CheckBilling::Plan => __('Included in your plan — no charge.'),
            $check->billing === CheckBilling::Reused => __('No charge — your saved score is still current.'),
            $hold !== null => __('You were not charged — the bureau did not return a new result.'),
            default => null,
        };
    }

    /**
     * What a check will cost this number's account, before the details are
     * submitted. Read-only: an account is looked up, never opened, here.
     */
    private function previewBilling(): ?string
    {
        $bureau = BureauName::from($this->bureau);

        if (! CreditCheckBilling::enabled() || ! SubmitCreditScoreCheck::isLive($bureau)) {
            return null;
        }

        $customer = $this->signedInCustomer?->verified_mobile === $this->verifiedMobileNumber
            ? $this->signedInCustomer
            : Customer::query()->where('verified_mobile', $this->verifiedMobileNumber)->first();

        if ($customer === null) {
            return null;
        }

        $quote = app(CreditCheckBilling::class)->quote($customer, $bureau);

        return match ($quote->mode) {
            CheckBilling::Plan => __('This check is included in your plan.'),
            CheckBilling::Wallet => __('Your free check for this period has been used (free again on :date). Unless your last score is still current, this check costs :total (:price + GST) from your wallet. Wallet balance: :balance.', [
                'date' => $quote->freeAgainAt?->translatedFormat('d M Y'),
                'total' => Wallet::format($quote->price->totalPaise()),
                'price' => Wallet::format($quote->price->taxablePaise),
                'balance' => Wallet::format($quote->balancePaise),
            ]),
            default => null,
        };
    }

    /**
     * The portal account for the number just verified: the signed-in customer's
     * own, else the one ResolveCustomerAccount finds or opens. A failure here is
     * logged and never stops the visitor's check.
     */
    private function accountFor(string $verifiedMobile, ResolveCustomerAccount $accounts): ?Customer
    {
        $signedIn = $this->signedInCustomer;

        if ($signedIn !== null && $signedIn->verified_mobile === $verifiedMobile) {
            return $signedIn;
        }

        try {
            return $accounts->forVerifiedMobile($verifiedMobile);
        } catch (\Throwable $exception) {
            Log::error('Customer account could not be resolved for a credit score check', ['exception' => $exception::class]);

            return null;
        }
    }

    public function restart(): void
    {
        $this->reset([
            'step', 'mobileNumber', 'otpCode', 'otpChallengeId', 'verifiedMobileNumber', 'resendAfterSeconds',
            'fullName', 'firstName', 'middleName', 'lastName', 'gender', 'dateOfBirth', 'panNumber', 'consent', 'resultScore', 'resultKind', 'resultError', 'resultInsights',
            'resultCheckId', 'accountStatus', 'billingNotice', 'paymentRequired', 'billingSummary',
        ]);

        unset($this->signedInCustomer, $this->lockedToAccount);

        if ($this->lockedToAccount) {
            $this->mobileNumber = (string) $this->signedInCustomer->verified_mobile;
        }
    }
};
?>

<x-ui.card :padded="false" class="relative overflow-hidden">
    @if ($step === 'mobile')
        <div class="flex items-center gap-2.5 bg-accent-soft px-6 py-3 text-sm text-accent-strong">
            <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 shrink-0" aria-hidden="true"><path d="M10 2a6 6 0 0 0-6 6c0 4-2 5.5-2 5.5h16S16 12 16 8a6 6 0 0 0-6-6ZM8.5 16a1.5 1.5 0 0 0 3 0h-3Z" /></svg>
            @if ($this->standInSource)
                {{ __('Check your free credit score in under 2 minutes') }}
            @else
                {{ __('Check your free :bureau score in under 2 minutes', ['bureau' => $this->bureauLabel]) }}
            @endif
        </div>
    @endif

    <div class="p-6 sm:p-7">
        <x-ui.stepper :steps="[__('Mobile number'), __('Verify OTP'), __('Your details'), __('Your score')]" :current="$this->stepIndex" />

        <div class="mt-7">
            @if ($step === 'mobile')
                <p class="font-display text-xl font-semibold text-ink">{{ __('Let\'s get started') }}</p>
                <div class="mt-4 flex flex-col gap-4">
                    @if ($this->lockedToAccount)
                        <div>
                            <p class="text-sm font-medium text-ink">{{ __('Mobile number') }}</p>
                            <p class="mt-1.5 rounded-lg border border-line bg-surface-2 px-3.5 py-2.5 font-mono text-sm text-ink-muted">+91 {{ $this->signedInCustomer->maskedMobile() }}</p>
                            @error('mobileNumber')
                                <p class="mt-1 text-xs text-warn">{{ $message }}</p>
                            @else
                                <p class="mt-1.5 text-xs text-ink-faint">{{ __('We\'ll send the OTP to your registered mobile number.') }}</p>
                            @enderror
                        </div>
                    @else
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
                    @endif
                    <p class="text-xs text-ink-faint">
                        {!! __('By continuing, you agree to FynnEdge\'s <a href=":terms_url" class="underline hover:text-ink">Terms</a> and <a href=":privacy_policy_url" class="underline hover:text-ink">Privacy Policy</a>.', ['terms_url' => e(route('terms')), 'privacy_policy_url' => e(route('privacy-policy'))]) !!}
                    </p>
                    <x-ui.button wire:click="sendOtp" wire:loading.attr="disabled" class="w-full justify-center">
                        {{ $this->standInSource ? __('Get Free Credit Score') : __('Get Free :bureau Score', ['bureau' => $this->bureauLabel]) }}
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" class="h-3.5 w-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8h10m0 0-4-4m4 4-4 4" /></svg>
                    </x-ui.button>
                    <p class="flex items-center justify-center gap-1.5 text-xs text-ink-faint">
                        <svg viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5 text-pass" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4L8 11.6l6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" /></svg>
                        @if ($this->standInSource)
                            {{ __(':bureau isn\'t connected yet — you\'ll see your real :source score instead, free', ['bureau' => $this->bureauLabel, 'source' => $this->standInSource]) }}
                        @elseif ($this->isLive)
                            {{ __('Your real :bureau score, free — checking your own score does not lower it', ['bureau' => $this->bureauLabel]) }}
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
                    @if ($this->takesSplitName)
                        <fieldset wire:key="name-split">
                            <legend class="text-sm font-medium text-ink">{{ __('Name (as per PAN)') }}</legend>
                            <p class="mt-0.5 text-xs text-ink-muted">{{ __('Enter your name exactly as printed on your PAN card.') }}</p>
                            <div class="mt-2 grid gap-3 sm:grid-cols-3">
                                @foreach ([
                                    'firstName' => ['label' => __('First name'), 'autocomplete' => 'given-name', 'optional' => false],
                                    'middleName' => ['label' => __('Middle name'), 'autocomplete' => 'additional-name', 'optional' => true],
                                    'lastName' => ['label' => __('Last name'), 'autocomplete' => 'family-name', 'optional' => false],
                                ] as $nameField => $nameMeta)
                                    <div wire:key="name-{{ $nameField }}">
                                        <label for="{{ $nameField }}" class="text-xs font-medium text-ink-muted">
                                            {{ $nameMeta['label'] }}
                                            @if ($nameMeta['optional']) <span class="font-normal text-ink-faint">{{ __('(optional)') }}</span> @endif
                                        </label>
                                        <input
                                            id="{{ $nameField }}"
                                            type="text"
                                            maxlength="100"
                                            autocomplete="{{ $nameMeta['autocomplete'] }}"
                                            wire:model="{{ $nameField }}"
                                            x-on:input="formatPanNameInput($event)"
                                            class="mt-1 w-full rounded-lg border border-line-strong bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-faint focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/30"
                                        >
                                        @error($nameField) <p class="mt-1 text-xs text-warn">{{ $message }}</p> @enderror
                                    </div>
                                @endforeach
                            </div>
                        </fieldset>
                    @else
                        <div wire:key="name-full">
                            <label for="fullName" class="text-sm font-medium text-ink">{{ __('Full name (as per PAN)') }}</label>
                            <input
                                id="fullName"
                                type="text"
                                maxlength="150"
                                autocomplete="name"
                                wire:model="fullName"
                                x-on:input="formatPanNameInput($event)"
                                class="mt-1.5 w-full rounded-lg border border-line-strong bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-faint focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/30"
                            >
                            @error('fullName')
                                <p class="mt-1 text-xs text-warn">{{ $message }}</p>
                            @else
                                <p class="mt-1.5 text-xs text-ink-faint">{{ __('Enter your name exactly as printed on your PAN card.') }}</p>
                            @enderror
                        </div>
                    @endif
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
                    @if ($this->needsGender)
                        <div>
                            <label for="gender" class="text-sm font-medium text-ink">{{ __('Gender') }}</label>
                            <select
                                id="gender"
                                wire:model="gender"
                                class="mt-1.5 w-full rounded-lg border border-line-strong bg-surface px-3.5 py-2.5 text-sm text-ink focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/30"
                            >
                                <option value="">{{ __('Select') }}</option>
                                <option value="male">{{ __('Male') }}</option>
                                <option value="female">{{ __('Female') }}</option>
                            </select>
                            @error('gender')
                                <p class="mt-1 text-xs text-warn">{{ $message }}</p>
                            @else
                                <p class="mt-1.5 text-xs text-ink-faint">{{ __(':bureau needs this to find your credit record.', ['bureau' => $this->bureauLabel]) }}</p>
                            @enderror
                        </div>
                    @endif
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
                    @if ($paymentRequired)
                        <div class="flex flex-col gap-3 rounded-xl border border-warn/25 bg-warn-soft p-4 text-sm" role="alert" data-payment-required>
                            <p class="font-semibold text-warn">{{ __('Add money to your wallet to refresh your score') }}</p>
                            <p class="text-ink-muted">
                                @if ($paymentRequired['free_again'])
                                    {{ __('Your free check for this period has been used — your next free check is on :date.', ['date' => $paymentRequired['free_again']]) }}
                                @endif
                                {{ __('This refresh costs :price + GST :gst = :total. Your wallet balance is :balance.', \Illuminate\Support\Arr::only($paymentRequired, ['price', 'gst', 'total', 'balance'])) }}
                            </p>
                            <div class="flex flex-col gap-2 sm:flex-row">
                                <x-ui.button tag="a" href="{{ route('portal.wallet') }}" target="_blank" rel="noopener" size="sm" class="justify-center">{{ __('Add money or buy a plan') }}</x-ui.button>
                            </div>
                            <p class="text-xs text-ink-faint">{{ __('Once your wallet is topped up, come back to this tab and press Check my score again — no new code needed.') }}</p>
                        </div>
                    @elseif ($billingNotice)
                        <x-ui.alert data-billing-notice>{{ $billingNotice }}</x-ui.alert>
                    @endif
                    <x-ui.button wire:click="submitDetails" wire:loading.attr="disabled" class="w-full justify-center">
                        {{ __('Check my score') }}
                    </x-ui.button>
                </div>
            @else
                {{-- Brings the dial into view when the result lands below a scrolled-past form. --}}
                <div class="flex scroll-mt-28 flex-col gap-6" x-data x-init="if ($el.getBoundingClientRect().top < 0) { $el.scrollIntoView({ behavior: 'smooth', block: 'start' }) }">
                    @if (in_array($resultKind, ['live', 'demo'], true))
                        <div class="flex flex-col items-center text-center">
                            @if ($resultKind === 'live')
                                <x-ui.badge tone="pass">
                                    <span class="relative flex h-2 w-2" aria-hidden="true">
                                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-pass opacity-60 motion-reduce:animate-none"></span>
                                        <span class="relative inline-flex h-2 w-2 rounded-full bg-pass"></span>
                                    </span>
                                    {{ __('Live score from :provider', ['provider' => $this->liveSource]) }}
                                </x-ui.badge>
                            @else
                                <x-ui.badge tone="warn">{{ __('Demo score — not your real credit report') }}</x-ui.badge>
                            @endif

                            <p class="mt-4 font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">
                                {{ __('Your :bureau score', ['bureau' => $resultKind === 'live' ? $this->scoreBureau : $this->bureauLabel]) }}
                            </p>
                            <x-site.credit-score-meter :score="$resultScore" class="mt-3" />

                            <p class="mt-4 max-w-sm text-sm text-ink-muted">{{ $this->advice->band()->verdict() }}</p>
                            <p class="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold text-accent">
                                @if ($this->advice->nextBandGap())
                                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" class="h-3.5 w-3.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 13V3m0 0L4 7m4-4 4 4" /></svg>
                                    {{ $this->advice->nextBandGap() }}
                                @else
                                    <svg viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5" aria-hidden="true"><path d="M10 1.8l2.5 5.1 5.6.8-4 3.9.9 5.6L10 14.6l-5 2.6.9-5.6-4-3.9 5.6-.8L10 1.8Z" /></svg>
                                    {{ __('You are in the top score band') }}
                                @endif
                            </p>
                        </div>

                        <ol class="grid grid-cols-4 gap-1.5" aria-label="{{ __('Score ranges') }}">
                            @foreach (ScoreBand::cases() as $legendBand)
                                <li @class([
                                    'rounded-lg px-1.5 py-2 text-center',
                                    'bg-surface-2 ring-1 ring-line-strong' => $legendBand === $this->advice->band(),
                                ])>
                                    <span class="mx-auto block h-1.5 w-8 rounded-full" style="background: var(--color-score-{{ $legendBand->value }})"></span>
                                    <span class="mt-1.5 block text-xs font-medium text-ink">{{ $legendBand->getLabel() }}</span>
                                    <span class="block font-mono text-[0.62rem] text-ink-faint">{{ $legendBand->min() }}–{{ $legendBand->max() }}</span>
                                </li>
                            @endforeach
                        </ol>

                        @if ($resultKind === 'demo')
                            <x-ui.alert tone="warn">
                                {{ __('This is a sample score for demonstration purposes — :bureau isn\'t connected yet, so this figure isn\'t pulled from a real credit bureau and shouldn\'t be relied on.', ['bureau' => $this->bureauLabel]) }}
                            </x-ui.alert>
                        @elseif ($this->standInSource)
                            <p class="text-center text-xs text-ink-faint">
                                {{ __(':bureau isn\'t connected yet, so this is your score from :source, another RBI-licensed credit bureau. Scores can differ a little between bureaus.', ['bureau' => $this->bureauLabel, 'source' => $this->standInSource]) }}
                            </p>
                        @endif

                        <x-site.credit-score-advice :advice="$this->advice" :bureau="$this->scoreBureau" class="border-t border-line pt-6" />
                    @elseif ($resultKind === 'no_score')
                        <div class="flex flex-col items-center text-center">
                            <x-ui.badge tone="muted">{{ __('No score yet') }}</x-ui.badge>
                            <x-site.credit-score-meter class="mt-4" />
                            <p class="mt-4 max-w-sm text-sm text-ink-muted">
                                {{ __(':bureau found your record but has no score for you yet. This usually means there is not enough credit history — a score appears a few months after your first loan or credit card.', ['bureau' => $this->scoreBureau]) }}
                            </p>
                        </div>

                        <x-site.credit-score-advice :advice="$this->advice" :bureau="$this->scoreBureau" class="border-t border-line pt-6" />
                    @else
                        <div class="flex flex-col items-center gap-4 py-4 text-center">
                            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-warn-soft text-warn" aria-hidden="true">
                                @if ($resultKind === 'pending')
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-7 w-7"><circle cx="12" cy="12" r="8.5" /><path stroke-linecap="round" d="M12 7.5V12l3 2" /></svg>
                                @else
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-7 w-7"><path stroke-linejoin="round" d="M10.3 4.3 2.9 17.2A2 2 0 0 0 4.6 20h14.8a2 2 0 0 0 1.7-2.8L13.7 4.3a2 2 0 0 0-3.4 0Z" /><path stroke-linecap="round" d="M12 9.5v4M12 16.5v.01" /></svg>
                                @endif
                            </span>
                            @if ($resultKind === 'pending')
                                <x-ui.badge tone="warn">{{ __('Still checking') }}</x-ui.badge>
                                <p class="max-w-sm text-sm text-ink-muted">
                                    {{ __('We could not confirm your score with :bureau just now. Please try again in an hour.', ['bureau' => $this->scoreBureau]) }}
                                </p>
                            @else
                                <x-ui.badge tone="warn">{{ __('Score not available') }}</x-ui.badge>
                                <p class="max-w-sm text-sm text-ink-muted">
                                    @if (in_array($resultError, [CrifErrorCode::NoRecordFound->value, CrifErrorCode::InvalidRequest->value, CrifErrorCode::ProviderRejected->value], true))
                                        {{ __(':bureau could not find a credit record matching these details. Check that your name and PAN are exactly as on your PAN card, and that this mobile number is the one your bank or lender has.', ['bureau' => $this->scoreBureau]) }}
                                    @else
                                        {{ __('We could not fetch your :bureau score right now. Please try again a little later.', ['bureau' => $this->scoreBureau]) }}
                                    @endif
                                </p>
                            @endif
                        </div>
                    @endif

                    @if ($billingSummary)
                        <p class="text-center text-sm text-ink-muted" data-billing-summary>{{ $billingSummary }}</p>
                    @endif

                    @if ($accountStatus === 'signed_in')
                        {{-- A plain link the visitor clicks, so a new tab is never blocked
                             as a pop-up; it carries no report data — the profile reads
                             the saved check server-side for the signed-in customer. --}}
                        <div class="flex flex-col gap-3 rounded-xl border border-line bg-surface-2 p-4 text-sm sm:flex-row sm:items-center sm:justify-between" data-portal-handoff>
                            <div>
                                <p class="font-medium text-ink">
                                    @if (in_array($resultKind, ['live', 'no_score'], true))
                                        {{ __('Saved to your :brand profile', ['brand' => config('customer_portal.brand')]) }}
                                    @else
                                        {{ __('You\'re signed in to your :brand profile', ['brand' => config('customer_portal.brand')]) }}
                                    @endif
                                </p>
                                <p class="mt-1 text-ink-muted">{{ __('Sign in any time with your mobile number to see your saved scores and reports — opening them never runs a new credit check.') }}</p>
                            </div>
                            @if ($context === 'portal')
                                @if ($resultCheckId && in_array($resultKind, ['live', 'no_score'], true))
                                    <x-ui.button tag="a" href="{{ route('portal.reports.show', $resultCheckId) }}" size="sm" class="shrink-0 justify-center">{{ __('View report') }}</x-ui.button>
                                @else
                                    <x-ui.button tag="a" href="{{ route('portal.credit-score') }}" size="sm" class="shrink-0 justify-center">{{ __('Back to my credit score') }}</x-ui.button>
                                @endif
                            @else
                                <x-ui.button tag="a" href="{{ route('portal.dashboard') }}" target="_blank" rel="noopener" size="sm" class="shrink-0 justify-center">
                                    {{ __('Open my profile') }}
                                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" class="h-3.5 w-3.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 3h4v4M13 3 7 9M11 9.5V13H3V5h3.5" /></svg>
                                </x-ui.button>
                            @endif
                        </div>
                    @elseif ($accountStatus === 'disabled')
                        <x-ui.alert tone="warn">{{ __('Access to your profile is switched off. Please contact us to restore it.') }}</x-ui.alert>
                    @endif

                    <div class="flex flex-col gap-2.5 border-t border-line pt-5 sm:flex-row sm:justify-center">
                        @if (in_array($resultKind, ['live', 'demo'], true))
                            <x-ui.button tag="a" href="{{ $context === 'portal' ? route('portal.apply') : route('eligibility.index') }}" class="justify-center">
                                {{ $context === 'portal' ? __('Apply for a Loan') : __('Check Eligibility') }}
                                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" class="h-3.5 w-3.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8h10m0 0-4-4m4 4-4 4" /></svg>
                            </x-ui.button>
                        @endif
                        <x-ui.button wire:click="restart" variant="secondary" class="justify-center">
                            {{ $this->lockedToAccount ? __('Start again') : __('Check another number') }}
                        </x-ui.button>
                    </div>

                    @if ($resultKind === 'live')
                        <p class="-mt-2 text-center text-xs text-ink-faint">
                            {{ __(':bureau scores range from 300 to 900 — the higher, the better your chances of a loan approval at a good rate. Checking your own score does not lower it.', ['bureau' => $this->scoreBureau]) }}
                        </p>
                    @endif
                </div>
            @endif
        </div>
    </div>

    {{--
        While the bureau is asked (a live check can take several seconds), the
        form gives way to the dial sweeping and each stage of the check ticking
        off in turn. Delayed, so a submit that fails validation never flashes it.
    --}}
    @if ($step === 'details')
        <div
            wire:loading.delay.flex
            wire:target="submitDetails"
            class="absolute inset-0 z-10 hidden flex-col items-center justify-center bg-surface px-6 py-8 text-center"
            role="status"
            aria-live="polite"
        >
            <x-ui.badge tone="accent">{{ __('Secure check in progress') }}</x-ui.badge>
            <p class="mt-4 font-display text-xl font-semibold text-ink">{{ __('Fetching your :bureau score', ['bureau' => $this->scoreBureau]) }}</p>
            <x-site.credit-score-meter fetching class="mt-5" />
            <ol class="mt-6 flex w-full max-w-xs flex-col gap-3 text-left">
                @foreach ([
                    __('Verifying your details'),
                    __('Connecting securely to :bureau', ['bureau' => $this->liveSource]),
                    __('Fetching your credit report'),
                    __('Analysing your report'),
                    __('Preparing your score'),
                ] as $stageIndex => $stage)
                    <li class="score-fetch-stage flex items-center gap-3 text-sm text-ink" style="--stage-start: {{ $stageIndex * 1.4 }}s">
                        <span class="relative flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-line-strong" aria-hidden="true">
                            <span class="score-fetch-spinner absolute -inset-px rounded-full border-2 border-accent border-t-transparent"></span>
                            <span class="score-fetch-check absolute -inset-px flex items-center justify-center rounded-full bg-pass text-white">
                                <svg viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4L8 11.6l6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" /></svg>
                            </span>
                        </span>
                        {{ $stage }}
                    </li>
                @endforeach
            </ol>
            <p class="mt-6 max-w-xs text-xs text-ink-faint">{{ __('This usually takes a few seconds. Please keep this page open.') }}</p>
        </div>
    @endif
</x-ui.card>
