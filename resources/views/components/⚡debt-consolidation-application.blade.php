<?php

use App\Enums\EmploymentType;
use App\Modules\Customers\Actions\LogInCustomer;
use App\Modules\Customers\Models\Customer;
use App\Modules\DebtConsolidation\Actions\OpenDebtApplication;
use App\Modules\DebtConsolidation\Actions\SaveDebtApplicationDraft;
use App\Modules\DebtConsolidation\Actions\SubmitDebtApplication;
use App\Modules\DebtConsolidation\DataTransferObjects\CaseAssessment;
use App\Modules\DebtConsolidation\Enums\ApplicantType;
use App\Modules\DebtConsolidation\Enums\BusinessConstitution;
use App\Modules\DebtConsolidation\Enums\CollateralType;
use App\Modules\DebtConsolidation\Enums\DebtApplicationStatus;
use App\Modules\DebtConsolidation\Enums\LiabilityType;
use App\Modules\DebtConsolidation\Models\DebtApplication;
use App\Modules\DebtConsolidation\Models\DebtCalculationSnapshot;
use App\Modules\DebtConsolidation\Models\DebtLiability;
use App\Modules\DebtConsolidation\Services\CaseInputBuilder;
use App\Modules\DebtConsolidation\Services\DebtConsolidationSettings;
use App\Modules\DebtConsolidation\Services\Finance\DebtConsolidationEngine;
use App\Modules\DebtConsolidation\Services\LiabilityRows;
use App\Modules\MobileOtp\Actions\RequestMobileOtp;
use App\Modules\MobileOtp\Actions\VerifyMobileOtp;
use App\Modules\MobileOtp\Enums\MobileOtpPurpose;
use App\Modules\MobileOtp\Enums\MobileOtpStatus;
use App\Modules\MobileOtp\Enums\MobileOtpVerificationResult;
use App\Modules\MobileOtp\Exceptions\MobileOtpThrottled;
use App\Modules\MobileOtp\Models\MobileOtpChallenge;
use App\Modules\MobileOtp\Services\MobileNumber;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/*
 * The Debt Consolidation application: who it is for → contact details → OTP
 * → loans → finances → employment or business details → collateral →
 * assessment review → consolidation options → consent and submit.
 *
 * Nothing is stored before the mobile number is verified. From then on the
 * case belongs to the customer's portal account (they are signed in, as on
 * the credit-score check) and every step is saved as they go, so they can
 * close the page and resume later — here, or from /my-profile. Each action
 * re-checks that the signed-in customer owns the case.
 */
new class extends Component
{
    public const STEPS = ['type', 'contact', 'otp', 'liabilities', 'finances', 'profile', 'collateral', 'review', 'options', 'consent', 'done'];

    #[Locked]
    public string $step = 'type';

    #[Locked]
    public ?string $applicationId = null;

    #[Locked]
    public ?string $reference = null;

    public string $applicantType = 'individual';

    /** @var array<string, string> */
    public array $contact = ['full_name' => '', 'email' => '', 'city' => '', 'pincode' => '', 'mobile' => ''];

    public string $otpCode = '';

    #[Locked]
    public ?string $otpChallengeId = null;

    #[Locked]
    public ?string $challengedNumber = null;

    #[Locked]
    public int $resendAfterSeconds = 0;

    /** @var array<int, array<string, string>> */
    public array $liabilities = [];

    /** @var array<string, string> */
    public array $finances = [];

    /** @var array<string, string> */
    public array $profile = [];

    /** @var array<int, array<string, string>> */
    public array $collaterals = [];

    public bool $consentAssessment = false;

    public bool $consentContact = false;

    #[Locked]
    public ?string $calculatorSnapshot = null;

    #[Locked]
    public ?string $sourceUrl = null;

    /** @var array<string, string> */
    #[Locked]
    public array $attribution = [];

    public function mount(): void
    {
        $this->sourceUrl = url()->current();
        $this->attribution = collect(['utm_source', 'utm_medium', 'utm_campaign'])
            ->mapWithKeys(fn (string $key): array => [$key => mb_substr((string) request()->query($key, ''), 0, 100)])
            ->filter()
            ->all();
        $this->applicantType = (DebtConsolidationSettings::availableApplicantTypes()[0] ?? ApplicantType::Individual)->value;
        $this->finances = $this->blankFinances();
        $this->profile = $this->blankProfile();

        $prefill = session('debt_consolidation.prefill');

        if (is_array($prefill) && DebtConsolidationSettings::acceptsApplicant(ApplicantType::tryFrom((string) ($prefill['applicant_type'] ?? '')) ?? ApplicantType::Individual)) {
            $this->applicantType = (string) $prefill['applicant_type'];
            $this->liabilities = array_values(array_filter((array) ($prefill['liabilities'] ?? []), 'is_array'));
            $this->finances = [...$this->finances, ...array_intersect_key((array) ($prefill['finances'] ?? []), $this->finances)];
            $this->calculatorSnapshot = $prefill['snapshot'] ?? null;
        }

        $customer = $this->customer();

        if ($customer !== null && filled($customer->verified_mobile)) {
            $this->contact = ['full_name' => (string) $customer->full_name, 'email' => (string) $customer->email, 'city' => (string) $customer->city, 'pincode' => (string) $customer->pincode, 'mobile' => (string) $customer->verified_mobile];

            $resumable = DebtApplication::query()
                ->ownedBy($customer)
                ->open()
                ->where(fn ($query) => $query->whereNull('submitted_at')->orWhere('status', DebtApplicationStatus::InformationPending))
                ->latest('id')
                ->first();

            if ($resumable !== null && $resumable->isEditableByCustomer()) {
                $this->load($resumable);
                $this->linkCalculatorSnapshot($resumable);
            }
        }

        if ($this->liabilities === []) {
            $this->liabilities = [LiabilityRows::blank($this->applicant())];
        }
    }

    public function chooseType(string $type): void
    {
        $chosen = ApplicantType::tryFrom($type);

        if ($chosen === null || $this->applicationId !== null) {
            return;
        }

        if (! DebtConsolidationSettings::acceptsApplicant($chosen)) {
            $this->addError('application', DebtConsolidationSettings::pausedMessage());

            return;
        }

        if ($chosen->value !== $this->applicantType) {
            $this->liabilities = [LiabilityRows::blank($chosen)];
        }

        $this->applicantType = $chosen->value;
        $this->step = 'contact';
    }

    public function submitContact(RequestMobileOtp $otp, OpenDebtApplication $open, LogInCustomer $logIn): void
    {
        if (! DebtConsolidationSettings::acceptsApplicant($this->applicant())) {
            $this->addError('application', DebtConsolidationSettings::pausedMessage());

            return;
        }

        $this->contact['mobile'] = MobileNumber::normalize((string) $this->contact['mobile']);

        $this->validate([
            'contact.full_name' => ['required', 'string', 'min:2', 'max:150'],
            'contact.email' => ['nullable', 'email', 'max:191'],
            'contact.city' => ['required', 'string', 'max:100'],
            'contact.pincode' => ['nullable', 'digits:6'],
            'contact.mobile' => ['required', 'regex:'.MobileNumber::PATTERN],
            'profile.business_name' => [$this->applicantType === 'business' ? 'required' : 'nullable', 'string', 'max:191'],
        ], [], [
            'contact.full_name' => __('name'),
            'contact.city' => __('city'),
            'contact.mobile' => __('mobile number'),
            'profile.business_name' => __('business name'),
        ]);

        $customer = $this->customer();

        if ($customer !== null && $customer->verified_mobile === $this->contact['mobile']) {
            $this->openApplication($open, $logIn, $this->contact['mobile']);

            return;
        }

        $this->sendOtp($otp);
    }

    public function sendOtp(RequestMobileOtp $action): void
    {
        $number = $this->step === 'otp' ? (string) $this->challengedNumber : $this->contact['mobile'];

        try {
            $challenge = $action->handle($number, MobileOtpPurpose::DebtConsolidation, request()->ip());
        } catch (MobileOtpThrottled $exception) {
            $this->addError($this->step === 'otp' ? 'otpCode' : 'contact.mobile', $exception->getMessage());

            return;
        }

        if ($challenge->status === MobileOtpStatus::Failed) {
            $this->addError($this->step === 'otp' ? 'otpCode' : 'contact.mobile', __('We could not send a verification code right now. Please try again shortly.'));

            return;
        }

        $this->otpChallengeId = $challenge->public_id;
        $this->challengedNumber = $number;
        $this->resendAfterSeconds = $challenge->resendAvailableInSeconds();
        $this->otpCode = '';
        $this->step = 'otp';
    }

    public function verifyOtp(VerifyMobileOtp $verify, OpenDebtApplication $open, LogInCustomer $logIn): void
    {
        if (! DebtConsolidationSettings::acceptsApplicant($this->applicant())) {
            $this->addError('application', DebtConsolidationSettings::pausedMessage());

            return;
        }

        $this->validate(['otpCode' => ['required', 'digits:'.MobileOtpChallenge::codeLength()]]);

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

        $this->openApplication($open, $logIn, (string) $this->challengedNumber);
    }

    public function changeContact(): void
    {
        if ($this->applicationId === null) {
            $this->reset('otpCode', 'otpChallengeId', 'challengedNumber', 'resendAfterSeconds');
            $this->step = 'contact';
        }
    }

    public function addLiability(): void
    {
        if (count($this->liabilities) < LiabilityRows::MAX_ROWS) {
            $this->liabilities[] = LiabilityRows::blank($this->applicant());
        }
    }

    public function removeLiability(int $index): void
    {
        unset($this->liabilities[$index]);
        $this->liabilities = array_values($this->liabilities);
        $this->autosave();
    }

    public function addCollateral(): void
    {
        if (count($this->collaterals) < 5) {
            $this->collaterals[] = ['key' => (string) Str::uuid(), 'collateral_type' => CollateralType::ResidentialProperty->value, 'description' => '', 'ownership' => 'self', 'estimated_value' => '', 'existing_charge_amount' => '', 'city' => ''];
        }
    }

    public function removeCollateral(int $index): void
    {
        unset($this->collaterals[$index]);
        $this->collaterals = array_values($this->collaterals);
    }

    /**
     * Auto-save: whatever is valid in the loan list is saved as the customer
     * types, so closing the tab loses nothing.
     */
    public function updatedLiabilities(): void
    {
        $this->liabilities = LiabilityRows::limit($this->liabilities);
        $this->autosave();
    }

    public function saveLiabilities(SaveDebtApplicationDraft $save): void
    {
        $application = $this->editableApplication();

        if ($application === null) {
            return;
        }

        $this->validate(LiabilityRows::rules('liabilities', $this->applicant()), [], [
            'liabilities.*.outstanding' => __('outstanding principal'),
            'liabilities.*.partial_amount' => __('amount to pay down'),
        ]);

        if (count(LiabilityRows::toInputs($this->liabilities)) === 0) {
            $this->addError('liabilities', __('Add at least one loan or card.'));

            return;
        }

        $save->liabilities($application, $this->liabilities);
        $this->goTo($save, 'finances');
    }

    public function saveFinances(SaveDebtApplicationDraft $save): void
    {
        $application = $this->editableApplication();

        if ($application === null) {
            return;
        }

        $money = ['nullable', 'numeric', 'min:0', 'max:100000000000'];
        $business = $this->applicantType === 'business';

        $this->validate([
            'finances.net_monthly_income' => [$business ? 'nullable' : 'required', 'numeric', 'gt:0', 'max:100000000000'],
            'finances.other_monthly_income' => $money,
            'finances.monthly_living_expenses' => $money,
            'finances.other_monthly_obligations' => $money,
            'finances.average_monthly_revenue' => [$business ? 'required' : 'nullable', 'numeric', 'gt:0', 'max:100000000000'],
            'finances.monthly_operating_expenses' => [$business ? 'required' : 'nullable', 'numeric', 'min:0', 'max:100000000000'],
            'finances.annual_turnover' => $money,
            'finances.annual_net_profit' => ['nullable', 'numeric', 'min:-100000000000', 'max:100000000000'],
        ], [], [
            'finances.net_monthly_income' => __('net monthly income'),
            'finances.average_monthly_revenue' => __('average monthly revenue'),
            'finances.monthly_operating_expenses' => __('monthly operating expenses'),
        ]);

        $save->finances($application, $this->finances);
        $this->goTo($save, 'profile');
    }

    public function saveProfile(SaveDebtApplicationDraft $save): void
    {
        $application = $this->editableApplication();

        if ($application === null) {
            return;
        }

        $business = $this->applicantType === 'business';

        $this->validate([
            'profile.employment_type' => [$business ? 'nullable' : 'required', Rule::enum(EmploymentType::class)],
            'profile.employer_name' => ['nullable', 'string', 'max:191'],
            'profile.occupation' => ['nullable', 'string', 'max:120'],
            'profile.date_of_birth' => [$business ? 'nullable' : 'required', 'date', 'before:-18 years', 'after:-100 years'],
            'profile.residence_type' => ['nullable', Rule::in(['owned', 'rented', 'family', 'company'])],
            'profile.declared_credit_score' => ['nullable', 'integer', 'min:300', 'max:900'],
            'profile.business_name' => [$business ? 'required' : 'nullable', 'string', 'max:191'],
            'profile.business_constitution' => [$business ? 'required' : 'nullable', Rule::enum(BusinessConstitution::class)],
            'profile.industry' => ['nullable', 'string', 'max:120'],
            'profile.business_vintage_months' => [$business ? 'required' : 'nullable', 'integer', 'min:0', 'max:1200'],
            'profile.gst_registered' => ['nullable', 'in:0,1'],
        ], [
            'profile.date_of_birth.date' => __('Enter a real date of birth as DD/MM/YYYY.'),
            'profile.date_of_birth.after' => __('Enter a real date of birth as DD/MM/YYYY.'),
            'profile.date_of_birth.before' => __('You must be at least 18 years old.'),
        ], [
            'profile.employment_type' => __('employment type'),
            'profile.date_of_birth' => __('date of birth'),
            'profile.business_constitution' => __('type of business'),
            'profile.business_vintage_months' => __('months in business'),
        ]);

        $save->profile($application, $this->profile);
        $this->goTo($save, 'collateral');
    }

    public function saveCollateral(SaveDebtApplicationDraft $save): void
    {
        $application = $this->editableApplication();

        if ($application === null) {
            return;
        }

        $this->validate([
            'collaterals' => ['array', 'max:5'],
            'collaterals.*.collateral_type' => ['required', Rule::enum(CollateralType::class)],
            'collaterals.*.description' => ['nullable', 'string', 'max:255'],
            'collaterals.*.estimated_value' => ['required', 'numeric', 'gt:0', 'max:100000000000'],
            'collaterals.*.existing_charge_amount' => ['nullable', 'numeric', 'min:0', 'max:100000000000'],
            'collaterals.*.city' => ['nullable', 'string', 'max:100'],
        ], [], ['collaterals.*.estimated_value' => __('estimated value')]);

        $save->collaterals($application, $this->collaterals);
        $this->goTo($save, 'review');
    }

    public function continueTo(string $step, SaveDebtApplicationDraft $save): void
    {
        if (in_array($step, ['review', 'options', 'consent'], true) && $this->applicationId !== null) {
            $this->goTo($save, $step);
        }
    }

    public function back(SaveDebtApplicationDraft $save): void
    {
        $order = ['finances' => 'liabilities', 'profile' => 'finances', 'collateral' => 'profile', 'review' => 'collateral', 'options' => 'review', 'consent' => 'options', 'contact' => 'type'];

        if (! isset($order[$this->step])) {
            return;
        }

        if ($this->applicationId === null) {
            $this->step = $order[$this->step];

            return;
        }

        $this->goTo($save, $order[$this->step]);
    }

    public function submit(SubmitDebtApplication $submit): void
    {
        $application = $this->editableApplication();

        if ($application === null) {
            return;
        }

        $this->validate([
            'consentAssessment' => ['accepted'],
            'consentContact' => ['accepted'],
        ], [
            'consentAssessment.accepted' => __('We need your consent to assess your information.'),
            'consentContact.accepted' => __('We need your consent to contact you about this request.'),
        ]);

        $submit->handle($application, request()->ip(), request()->userAgent());

        $this->reference = $application->reference;
        $this->step = 'done';
    }

    #[Computed]
    public function assessment(): ?CaseAssessment
    {
        if ($this->applicationId === null || ! in_array($this->step, ['review', 'options', 'consent'], true)) {
            return null;
        }

        [$case] = app(CaseInputBuilder::class)->forApplication($this->application());

        return $case->liabilities === [] ? null : app(DebtConsolidationEngine::class)->assess($case);
    }

    /**
     * @return list<LiabilityType>
     */
    #[Computed]
    public function liabilityTypes(): array
    {
        return LiabilityType::forApplicant($this->applicant());
    }

    private function openApplication(OpenDebtApplication $open, LogInCustomer $logIn, string $mobile): void
    {
        $application = $open->handle($mobile, $this->applicant(), [
            'full_name' => trim($this->contact['full_name']),
            'email' => filled($this->contact['email']) ? trim($this->contact['email']) : null,
            'city' => trim($this->contact['city']),
            'source_url' => $this->sourceUrl,
            ...$this->attribution,
        ]);

        $application->forceFill(['pincode' => filled($this->contact['pincode']) ? $this->contact['pincode'] : $application->pincode])->save();

        if ($application->customer !== null && ! $this->customer()?->is($application->customer)) {
            $logIn->handle($application->customer, 'otp');
        }

        $this->applicationId = $application->public_id;
        $this->reference = $application->reference;
        $save = app(SaveDebtApplicationDraft::class);

        if ($this->applicantType === 'business' && filled($this->profile['business_name'] ?? null)) {
            $save->profile($application, ['business_name' => $this->profile['business_name']]);
        }

        if ($application->liabilities()->doesntExist() && LiabilityRows::toInputs($this->liabilities) !== []) {
            $save->liabilities($application, $this->liabilities);
        }

        $this->linkCalculatorSnapshot($application);
        $this->load($application->fresh());
        $this->goTo($save, 'liabilities');
    }

    private function load(DebtApplication $application): void
    {
        $application->loadMissing(['liabilities', 'financialProfile', 'collaterals']);
        $this->applicationId = $application->public_id;
        $this->reference = $application->reference;
        $this->applicantType = $application->applicant_type->value;
        $this->contact = ['full_name' => (string) $application->full_name, 'email' => (string) $application->email, 'city' => (string) $application->city, 'pincode' => (string) $application->pincode, 'mobile' => $application->mobile];

        if ($application->liabilities->isNotEmpty()) {
            $this->liabilities = $application->liabilities->map(fn (DebtLiability $liability): array => LiabilityRows::fromModel($liability))->values()->all();
        }

        $profile = $application->financialProfile;
        $value = fn (mixed $v): string => $v === null ? '' : (string) (is_numeric($v) && str_contains((string) $v, '.') ? rtrim(rtrim((string) $v, '0'), '.') : $v);

        if ($profile !== null) {
            foreach (array_keys($this->finances) as $field) {
                $this->finances[$field] = $value($profile->{$field});
            }

            $this->profile = [
                ...$this->profile,
                'employment_type' => (string) $profile->employment_type?->value,
                'employer_name' => (string) $profile->employer_name,
                'occupation' => (string) $profile->occupation,
                'date_of_birth' => (string) $profile->date_of_birth?->toDateString(),
                'residence_type' => (string) $profile->residence_type,
                'declared_credit_score' => $value($profile->declared_credit_score),
                'business_name' => (string) $profile->business_name,
                'business_constitution' => (string) $profile->business_constitution?->value,
                'industry' => (string) $profile->industry,
                'business_vintage_months' => $value($profile->business_vintage_months),
                'gst_registered' => $profile->gst_registered === null ? '' : ($profile->gst_registered ? '1' : '0'),
            ];
        }

        $this->collaterals = $application->collaterals->map(fn ($collateral): array => [
            'key' => $collateral->public_id,
            'collateral_type' => $collateral->collateral_type->value,
            'description' => (string) $collateral->description,
            'ownership' => (string) $collateral->ownership,
            'estimated_value' => $value($collateral->estimated_value),
            'existing_charge_amount' => $value($collateral->existing_charge_amount),
            'city' => (string) $collateral->city,
        ])->values()->all();

        $this->step = in_array($application->journey_step, ['liabilities', 'finances', 'profile', 'collateral', 'review', 'options', 'consent'], true)
            ? $application->journey_step
            : 'liabilities';
    }

    private function goTo(SaveDebtApplicationDraft $save, string $step): void
    {
        $save->step($this->application(), $step);
        $this->step = $step;
        unset($this->assessment);
    }

    private function autosave(): void
    {
        if ($this->applicationId === null) {
            return;
        }

        $application = $this->application();

        if ($application->isEditableByCustomer() && DebtConsolidationSettings::acceptsApplicant($application->applicant_type)) {
            app(SaveDebtApplicationDraft::class)->liabilities($application, $this->liabilities);
        }
    }

    /**
     * The calculator figures the visitor carried over are kept with the case
     * they continue (new or resumed), once.
     */
    private function linkCalculatorSnapshot(DebtApplication $application): void
    {
        if ($this->calculatorSnapshot === null) {
            return;
        }

        DebtCalculationSnapshot::query()->where('public_id', $this->calculatorSnapshot)->whereNull('debt_application_id')
            ->update(['debt_application_id' => $application->getKey()]);
        session()->forget('debt_consolidation.prefill');
        $this->calculatorSnapshot = null;
    }

    /**
     * The case, when the customer may still change it and requests are being
     * accepted; otherwise the reason is shown and null returned (never a 500).
     */
    private function editableApplication(): ?DebtApplication
    {
        $application = $this->application();

        if (! $application->isEditableByCustomer()) {
            $this->addError('application', __('This application has already been submitted, so it can no longer be changed here. You can follow it in My Profile.'));

            return null;
        }

        if (! DebtConsolidationSettings::acceptsApplicant($application->applicant_type)) {
            $this->addError('application', DebtConsolidationSettings::pausedMessage());

            return null;
        }

        return $application;
    }

    /**
     * The case, only for the signed-in customer who owns it.
     */
    private function application(): DebtApplication
    {
        $customer = $this->customer();
        $application = $this->applicationId === null ? null : DebtApplication::query()->where('public_id', $this->applicationId)->first();

        abort_unless($customer !== null && $application !== null && $application->isOwnedBy($customer), 403);

        return $application;
    }

    private function customer(): ?Customer
    {
        $customer = Auth::guard('customer')->user();

        return $customer instanceof Customer && $customer->hasPortalAccess() ? $customer : null;
    }

    private function applicant(): ApplicantType
    {
        return ApplicantType::tryFrom($this->applicantType) ?? ApplicantType::Individual;
    }

    /**
     * @return array<string, string>
     */
    private function blankFinances(): array
    {
        return array_fill_keys(['net_monthly_income', 'other_monthly_income', 'monthly_living_expenses', 'other_monthly_obligations', 'average_monthly_revenue', 'monthly_operating_expenses', 'annual_turnover', 'annual_net_profit'], '');
    }

    /**
     * @return array<string, string>
     */
    private function blankProfile(): array
    {
        return array_fill_keys(['employment_type', 'employer_name', 'occupation', 'date_of_birth', 'residence_type', 'declared_credit_score', 'business_name', 'business_constitution', 'industry', 'business_vintage_months', 'gst_registered'], '');
    }
};
?>

@php
    use App\Modules\DebtConsolidation\Services\DebtPresenter as P;
    use App\Modules\DebtConsolidation\Services\DebtConsolidationSettings as S;

    $business = $applicantType === 'business';
    $titles = [
        'type' => __('Who is it for?'),
        'contact' => __('Your details'),
        'otp' => __('Verify mobile'),
        'liabilities' => __('Your loans'),
        'finances' => $business ? __('Cash flow') : __('Income'),
        'profile' => $business ? __('Business') : __('Employment'),
        'collateral' => __('Security'),
        'review' => __('Assessment'),
        'options' => __('Options'),
        'consent' => __('Consent'),
    ];
    $visibleSteps = array_keys($titles);
    $current = array_search($step === 'done' ? 'consent' : $step, $visibleSteps, true);
    $input = 'mt-1 w-full rounded-lg border border-line-strong bg-surface px-3 py-2 text-sm text-ink placeholder:text-ink-faint focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/30';
    $label = 'text-xs font-medium text-ink-muted';
    $assessment = $this->assessment;
@endphp

<div class="mx-auto max-w-4xl">
    @if ($step !== 'done')
        <div class="mb-6">
            <div class="flex items-center justify-between text-xs text-ink-muted">
                <span>{{ __('Step :current of :total — :title', ['current' => $current + 1, 'total' => count($visibleSteps), 'title' => $titles[$step]]) }}</span>
                @if ($reference)
                    <span>{{ __('Reference :reference · saved automatically', ['reference' => $reference]) }}</span>
                @endif
            </div>
            <div class="mt-2 h-1.5 rounded-full bg-surface-2" role="progressbar" aria-valuemin="1" aria-valuemax="{{ count($visibleSteps) }}" aria-valuenow="{{ $current + 1 }}">
                <div class="h-1.5 rounded-full bg-accent transition-all" style="width: {{ round(($current + 1) / count($visibleSteps) * 100) }}%"></div>
            </div>
        </div>
    @endif

    <div class="rounded-2xl border border-line bg-surface p-5 shadow-sm sm:p-8">
        @error('application') <x-ui.alert tone="warn" class="mb-4">{{ $message }}</x-ui.alert> @enderror

        @if ($step === 'type')
            <h2 class="font-display text-xl font-semibold text-ink">{{ __('Who are you consolidating debt for?') }}</h2>
            <p class="mt-1 text-sm text-ink-muted">{{ __('About five minutes. Nothing is saved until you verify your mobile number.') }}</p>
            @if ($liabilities !== [] && filled($liabilities[0]['outstanding'] ?? null))
                <p class="mt-3 rounded-lg bg-accent-soft p-3 text-sm text-ink">{{ __('We have carried over the loans you entered in the calculator.') }}</p>
            @endif
            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                @foreach (S::availableApplicantTypes() as $type)
                    <button type="button" wire:click="chooseType('{{ $type->value }}')" class="rounded-2xl border border-line p-5 text-left transition-colors hover:border-accent hover:bg-accent-soft">
                        <span class="font-display text-lg font-semibold text-ink">{{ $type->value === 'business' ? __('My business') : __('Me / my family') }}</span>
                        <span class="mt-1 block text-sm text-ink-muted">{{ $type->value === 'business' ? __('Business loans, machinery and vehicle loans, working capital, invoice or merchant finance.') : __('Personal loans, credit cards, consumer EMIs, vehicle, gold, education or home loans.') }}</span>
                    </button>
                @endforeach
            </div>
        @elseif ($step === 'contact')
            <form wire:submit="submitContact" class="flex flex-col gap-4">
                <h2 class="font-display text-xl font-semibold text-ink">{{ __('Your details') }}</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    @if ($business)
                        <label class="block sm:col-span-2"><span class="{{ $label }}">{{ __('Business name') }}</span><input type="text" wire:model="profile.business_name" class="{{ $input }}" autocomplete="organization">@error('profile.business_name') <span class="mt-1 block text-xs text-warn">{{ $message }}</span> @enderror</label>
                    @endif
                    <label class="block"><span class="{{ $label }}">{{ $business ? __('Your name (owner or director)') : __('Full name') }}</span><input type="text" wire:model="contact.full_name" class="{{ $input }}" autocomplete="name">@error('contact.full_name') <span class="mt-1 block text-xs text-warn">{{ $message }}</span> @enderror</label>
                    <label class="block"><span class="{{ $label }}">{{ __('Mobile number') }}</span><input type="tel" inputmode="numeric" maxlength="10" wire:model="contact.mobile" class="{{ $input }}" autocomplete="tel-national" placeholder="{{ __('10-digit mobile number') }}">@error('contact.mobile') <span class="mt-1 block text-xs text-warn">{{ $message }}</span> @enderror</label>
                    <label class="block"><span class="{{ $label }}">{{ __('Email (optional)') }}</span><input type="email" wire:model="contact.email" class="{{ $input }}" autocomplete="email">@error('contact.email') <span class="mt-1 block text-xs text-warn">{{ $message }}</span> @enderror</label>
                    <label class="block"><span class="{{ $label }}">{{ __('City') }}</span><input type="text" wire:model="contact.city" class="{{ $input }}" autocomplete="address-level2">@error('contact.city') <span class="mt-1 block text-xs text-warn">{{ $message }}</span> @enderror</label>
                    <label class="block"><span class="{{ $label }}">{{ __('PIN code (optional)') }}</span><input type="text" inputmode="numeric" maxlength="6" wire:model="contact.pincode" class="{{ $input }}" autocomplete="postal-code">@error('contact.pincode') <span class="mt-1 block text-xs text-warn">{{ $message }}</span> @enderror</label>
                </div>
                <div class="flex flex-wrap gap-3">
                    <x-ui.button type="submit" wire:loading.attr="disabled">{{ __('Continue') }}</x-ui.button>
                    <x-ui.button type="button" wire:click="back" variant="ghost">{{ __('Back') }}</x-ui.button>
                </div>
            </form>
        @elseif ($step === 'otp')
            <form wire:submit="verifyOtp" class="flex flex-col gap-4">
                <h2 class="font-display text-xl font-semibold text-ink">{{ __('Verify your mobile number') }}</h2>
                <p class="text-sm text-ink-muted">{!! __('We have sent a :length-digit code to <span class="font-medium text-ink">+91 :number</span>.', ['length' => e(\App\Modules\MobileOtp\Models\MobileOtpChallenge::codeLength()), 'number' => e(\App\Modules\MobileOtp\Services\MobileNumber::mask((string) $challengedNumber))]) !!}</p>
                <label class="block max-w-xs"><span class="{{ $label }}">{{ __('One-time code') }}</span><input type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="{{ \App\Modules\MobileOtp\Models\MobileOtpChallenge::codeLength() }}" wire:model="otpCode" class="{{ $input }} tracking-[0.3em]">@error('otpCode') <span class="mt-1 block text-xs text-warn">{{ $message }}</span> @enderror</label>
                <div class="flex flex-wrap items-center gap-3" wire:key="dc-app-resend-{{ $otpChallengeId }}" x-data="otpResendCountdown(@js($resendAfterSeconds))">
                    <x-ui.button type="submit" wire:loading.attr="disabled">{{ __('Verify & continue') }}</x-ui.button>
                    <x-ui.button type="button" wire:click="sendOtp" wire:loading.attr="disabled" ::disabled="resendIn > 0" variant="ghost" size="sm"><span x-text="resendIn > 0 ? @js(__('Resend code in :time')).replace(':time', `${resendIn}s`) : @js(__('Resend code'))">{{ __('Resend code') }}</span></x-ui.button>
                    <x-ui.button type="button" wire:click="changeContact" variant="ghost" size="sm">{{ __('Change details') }}</x-ui.button>
                </div>
            </form>
        @elseif ($step === 'liabilities')
            <h2 class="font-display text-xl font-semibold text-ink">{{ __('List every loan and card you are repaying') }}</h2>
            <p class="mt-1 text-sm text-ink-muted">{{ __('Include the ones you want to keep — they still count towards what you can afford. Enter figures from your latest statements; there is no need to upload anything now.') }}</p>
            @error('liabilities') <p class="mt-3 text-sm text-warn">{{ $message }}</p> @enderror
            <div class="mt-5"><x-debt.liability-editor :rows="$liabilities" :types="$this->liabilityTypes" model="liabilities" :live="false" /></div>
            <div class="mt-6 flex flex-wrap gap-3"><x-ui.button type="button" wire:click="saveLiabilities" wire:loading.attr="disabled">{{ __('Save and continue') }}</x-ui.button></div>
        @elseif ($step === 'finances')
            <form wire:submit="saveFinances" class="flex flex-col gap-4">
                <h2 class="font-display text-xl font-semibold text-ink">{{ $business ? __('Your business cash flow') : __('Your monthly income') }}</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    @php
                        $fields = $business
                            ? [['average_monthly_revenue', __('Average monthly revenue / bank credits (₹)')], ['monthly_operating_expenses', __('Monthly operating expenses, excluding loan repayments (₹)')], ['annual_turnover', __('Annual turnover (₹, optional)')], ['annual_net_profit', __('Annual net profit (₹, optional)')], ['other_monthly_obligations', __('Other fixed monthly commitments (₹, optional)')]]
                            : [['net_monthly_income', __('Net monthly income after tax (₹)')], ['other_monthly_income', __('Other regular monthly income (₹, optional)')], ['monthly_living_expenses', __('Monthly living expenses incl. rent (₹, optional)')], ['other_monthly_obligations', __('Other fixed monthly commitments not listed as loans (₹, optional)')]];
                    @endphp
                    @foreach ($fields as [$field, $fieldLabel])
                        <label class="block"><span class="{{ $label }}">{{ $fieldLabel }}</span><input type="text" inputmode="decimal" wire:model="finances.{{ $field }}" class="{{ $input }}">@error('finances.'.$field) <span class="mt-1 block text-xs text-warn">{{ $message }}</span> @enderror</label>
                    @endforeach
                </div>
                <div class="flex flex-wrap gap-3"><x-ui.button type="submit">{{ __('Save and continue') }}</x-ui.button><x-ui.button type="button" wire:click="back" variant="ghost">{{ __('Back') }}</x-ui.button></div>
            </form>
        @elseif ($step === 'profile')
            <form wire:submit="saveProfile" class="flex flex-col gap-4">
                <h2 class="font-display text-xl font-semibold text-ink">{{ $business ? __('About your business') : __('About your work') }}</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    @if ($business)
                        <label class="block"><span class="{{ $label }}">{{ __('Business name') }}</span><input type="text" wire:model="profile.business_name" class="{{ $input }}">@error('profile.business_name') <span class="mt-1 block text-xs text-warn">{{ $message }}</span> @enderror</label>
                        <label class="block"><span class="{{ $label }}">{{ __('Type of business') }}</span><select wire:model="profile.business_constitution" class="{{ $input }}"><option value="">{{ __('Choose…') }}</option>@foreach (\App\Modules\DebtConsolidation\Enums\BusinessConstitution::cases() as $case)<option value="{{ $case->value }}">{{ $case->getLabel() }}</option>@endforeach</select>@error('profile.business_constitution') <span class="mt-1 block text-xs text-warn">{{ $message }}</span> @enderror</label>
                        <label class="block"><span class="{{ $label }}">{{ __('Industry') }}</span><input type="text" wire:model="profile.industry" class="{{ $input }}"></label>
                        <label class="block"><span class="{{ $label }}">{{ __('Months in business') }}</span><input type="text" inputmode="numeric" wire:model="profile.business_vintage_months" class="{{ $input }}">@error('profile.business_vintage_months') <span class="mt-1 block text-xs text-warn">{{ $message }}</span> @enderror</label>
                        <label class="block"><span class="{{ $label }}">{{ __('GST registered?') }}</span><select wire:model="profile.gst_registered" class="{{ $input }}"><option value="">{{ __('Choose…') }}</option><option value="1">{{ __('Yes') }}</option><option value="0">{{ __('No') }}</option></select></label>
                    @else
                        <label class="block"><span class="{{ $label }}">{{ __('Employment') }}</span><select wire:model="profile.employment_type" class="{{ $input }}"><option value="">{{ __('Choose…') }}</option>@foreach (\App\Enums\EmploymentType::cases() as $case)<option value="{{ $case->value }}">{{ $case->getLabel() }}</option>@endforeach</select>@error('profile.employment_type') <span class="mt-1 block text-xs text-warn">{{ $message }}</span> @enderror</label>
                        <label class="block"><span class="{{ $label }}">{{ __('Employer or business name (optional)') }}</span><input type="text" wire:model="profile.employer_name" class="{{ $input }}"></label>
                        {{-- Typed DD/MM/YYYY, as on the credit-score form: a native date picker shows the
                             browser's locale format (MM/DD/YYYY on many) and accepts years like 0001. The
                             hidden input carries the ISO date the validation rules and the column expect. --}}
                        <label class="block" x-data="{ raw: isoToDateInputDigits(@js((string) ($profile['date_of_birth'] ?? ''))) }" x-init="$refs.dobDisplay.value = formatDateInput(raw)">
                            <span class="{{ $label }}">{{ __('Date of birth') }}</span>
                            <input
                                type="text"
                                inputmode="numeric"
                                autocomplete="off"
                                placeholder="{{ __('DD/MM/YYYY') }}"
                                maxlength="10"
                                x-ref="dobDisplay"
                                x-on:input="
                                    raw = $el.value.replace(/[^0-9]/g, '').slice(0, 8);
                                    $el.value = formatDateInput(raw);
                                    $refs.dobHidden.value = dateInputToIso(raw);
                                    $refs.dobHidden.dispatchEvent(new Event('input', { bubbles: true }));
                                "
                                class="{{ $input }}"
                                data-dob-input
                            >
                            <input type="hidden" x-ref="dobHidden" wire:model="profile.date_of_birth">
                            @error('profile.date_of_birth') <span class="mt-1 block text-xs text-warn">{{ $message }}</span> @enderror
                        </label>
                        <label class="block"><span class="{{ $label }}">{{ __('Home') }}</span><select wire:model="profile.residence_type" class="{{ $input }}"><option value="">{{ __('Choose…') }}</option><option value="owned">{{ __('Owned') }}</option><option value="rented">{{ __('Rented') }}</option><option value="family">{{ __('Family-owned') }}</option><option value="company">{{ __('Company-provided') }}</option></select></label>
                    @endif
                    <label class="block"><span class="{{ $label }}">{{ __('Credit score, if you know it (optional)') }}</span><input type="text" inputmode="numeric" wire:model="profile.declared_credit_score" class="{{ $input }}">@error('profile.declared_credit_score') <span class="mt-1 block text-xs text-warn">{{ $message }}</span> @enderror</label>
                </div>
                <div class="flex flex-wrap gap-3"><x-ui.button type="submit">{{ __('Save and continue') }}</x-ui.button><x-ui.button type="button" wire:click="back" variant="ghost">{{ __('Back') }}</x-ui.button></div>
            </form>
        @elseif ($step === 'collateral')
            <form wire:submit="saveCollateral" class="flex flex-col gap-4">
                <h2 class="font-display text-xl font-semibold text-ink">{{ __('Property or assets you could offer as security (optional)') }}</h2>
                <p class="text-sm text-ink-muted">{{ __('A secured loan (for example against property) usually costs less, but the asset is at risk if repayments are missed. Skip this if you prefer an unsecured loan.') }}</p>
                @foreach ($collaterals as $index => $collateral)
                    <fieldset wire:key="collateral-{{ $collateral['key'] }}" class="grid gap-3 rounded-xl border border-line p-4 sm:grid-cols-2">
                        <label class="block"><span class="{{ $label }}">{{ __('Type') }}</span><select wire:model="collaterals.{{ $index }}.collateral_type" class="{{ $input }}">@foreach (\App\Modules\DebtConsolidation\Enums\CollateralType::cases() as $case)<option value="{{ $case->value }}">{{ $case->getLabel() }}</option>@endforeach</select></label>
                        <label class="block"><span class="{{ $label }}">{{ __('Estimated value (₹)') }}</span><input type="text" inputmode="decimal" wire:model="collaterals.{{ $index }}.estimated_value" class="{{ $input }}">@error('collaterals.'.$index.'.estimated_value') <span class="mt-1 block text-xs text-warn">{{ $message }}</span> @enderror</label>
                        <label class="block"><span class="{{ $label }}">{{ __('Loans already secured on it (₹)') }}</span><input type="text" inputmode="decimal" wire:model="collaterals.{{ $index }}.existing_charge_amount" class="{{ $input }}"></label>
                        <label class="block"><span class="{{ $label }}">{{ __('City') }}</span><input type="text" wire:model="collaterals.{{ $index }}.city" class="{{ $input }}"></label>
                        <label class="block sm:col-span-2"><span class="{{ $label }}">{{ __('Short description') }}</span><input type="text" wire:model="collaterals.{{ $index }}.description" class="{{ $input }}" placeholder="{{ __('e.g. 2 BHK flat in my name') }}"></label>
                        <button type="button" wire:click="removeCollateral({{ $index }})" class="justify-self-start text-xs font-medium text-warn hover:underline">{{ __('Remove') }}</button>
                    </fieldset>
                @endforeach
                @if (count($collaterals) < 5)
                    <button type="button" wire:click="addCollateral" class="self-start rounded-full border border-dashed border-accent px-4 py-2 text-sm font-semibold text-accent hover:bg-accent-soft">+ {{ __('Add an asset') }}</button>
                @endif
                <div class="flex flex-wrap gap-3"><x-ui.button type="submit">{{ $collaterals === [] ? __('Skip — no security') : __('Save and continue') }}</x-ui.button><x-ui.button type="button" wire:click="back" variant="ghost">{{ __('Back') }}</x-ui.button></div>
            </form>
        @elseif (in_array($step, ['review', 'options', 'consent'], true))
            @if ($assessment === null)
                <p class="text-sm text-warn">{{ __('Add at least one loan to see your assessment.') }}</p>
            @else
                @php
                    $names = P::liabilityNames(array_map(fn ($row) => ['key' => $row['key'], 'lender_name' => $row['lender_name'], 'type' => $row['type']], $liabilities));
                    $portfolio = $assessment->portfolio;
                    $now = $assessment->currentAffordability;
                @endphp
                @if ($step === 'review')
                    <h2 class="font-display text-xl font-semibold text-ink">{{ __('Your debt today') }}</h2>
                    <dl class="mt-5 grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                        @foreach ([[__('Total debt'), P::inr($portfolio->totalOutstanding)], [__('Loans and cards'), (string) $portfolio->liabilityCount], [__('Monthly repayments'), P::inr($portfolio->totalMonthlyObligation)], [__('Average interest rate'), P::percent($portfolio->weightedAverageRate)], [__('Interest still to pay'), P::inr($portfolio->remainingInterest)], [__('Secured debt'), P::percent($portfolio->securedShare ?? 0)], [$now->metric === 'foir' ? __('Income going to EMIs (FOIR)') : __('Debt-service cover'), $now->metric === 'foir' ? P::percent($now->currentValue) : ($now->currentValue === null ? '—' : $now->currentValue.'×')], [__('Affordability'), $now->currentBand->getLabel()]] as [$term, $value])
                            <div class="rounded-xl bg-surface-2 p-3"><dt class="text-xs text-ink-muted">{{ $term }}</dt><dd class="mt-1 font-semibold text-ink">{{ $value }}</dd></div>
                        @endforeach
                    </dl>
                    @php
                        $caseFlags = array_values(array_filter($assessment->flags, fn ($flag) => $flag->flag->severity() !== 'info' || $flag->flag === \App\Modules\DebtConsolidation\Enums\RiskFlag::RateMismatch));
                    @endphp
                    @if ($caseFlags !== [])
                        <ul class="mt-5 flex flex-col gap-2">
                            @foreach ($caseFlags as $flag)
                                <li class="flex gap-2 rounded-lg bg-warn-soft p-3 text-sm text-ink"><span aria-hidden="true">⚠</span>{{ P::flagSentence($flag->toArray(), $names) }}</li>
                            @endforeach
                        </ul>
                    @endif
                    <div class="mt-6 flex flex-wrap gap-3"><x-ui.button type="button" wire:click="continueTo('options')">{{ __('See consolidation options') }}</x-ui.button><x-ui.button type="button" wire:click="back" variant="ghost">{{ __('Back') }}</x-ui.button></div>
                @elseif ($step === 'options')
                    <h2 class="font-display text-xl font-semibold text-ink">{{ __('Would consolidating help?') }}</h2>
                    <p class="mt-1 text-sm text-ink-muted">{{ __('Estimates on indicative terms. An advisor will refine them with real lender offers.') }}</p>
                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        @foreach ($assessment->scenarios as $row)
                            @php
                                $result = $row['result'];
                                $suitability = $row['suitability'];
                            @endphp
                            <div class="flex flex-col rounded-2xl border border-line p-5">
                                <p class="font-display font-semibold text-ink">{{ __($row['scenario']->label) }}</p>
                                <p class="text-xs text-ink-muted">{{ __(':rate% a year over :tenure', ['rate' => rtrim(rtrim(number_format($result->annualInterestRate, 2), '0'), '.'), 'tenure' => P::months($result->tenureMonths)]) }}</p>
                                <p @class(['mt-4 font-display text-2xl font-semibold', 'text-pass' => $result->netFinancialBenefit > 0, 'text-warn' => $result->netFinancialBenefit <= 0])>{{ $result->netFinancialBenefit > 0 ? __('Save :amount', ['amount' => P::inr($result->netFinancialBenefit)]) : __('Costs :amount more', ['amount' => P::inr(abs($result->netFinancialBenefit))]) }}</p>
                                <dl class="mt-3 grid grid-cols-2 gap-2 text-xs">
                                    <div><dt class="text-ink-muted">{{ __('Monthly outgo') }}</dt><dd class="font-semibold text-ink">{{ P::inr($result->existingMonthlyOutflow) }} → {{ P::inr($result->proposedMonthlyOutflow) }}</dd></div>
                                    <div><dt class="text-ink-muted">{{ __('Debt-free in') }}</dt><dd class="font-semibold text-ink">{{ P::months($result->existingMonths) }} → {{ P::months($result->proposedMonths) }}</dd></div>
                                    <div><dt class="text-ink-muted">{{ __('New loan amount') }}</dt><dd class="font-semibold text-ink">{{ P::inr($result->loanAmount) }}</dd></div>
                                    <div><dt class="text-ink-muted">{{ __('Fees and charges') }}</dt><dd class="font-semibold text-ink">{{ P::inr($result->totalConsolidationCosts) }}</dd></div>
                                </dl>
                                <p class="mt-3 text-sm text-ink"><span class="font-semibold">{{ number_format($suitability->score, 0) }}/100</span> · {{ $suitability->band->getLabel() }}</p>
                                @foreach (array_slice(array_values(array_filter($result->flags, fn ($flag) => $flag->flag->severity() === 'critical')), 0, 2) as $flag)
                                    <p class="mt-2 text-xs text-warn">{{ P::flagSentence($flag->toArray(), $names) }}</p>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                    <p class="mt-4 text-xs text-ink-muted">{{ S::text('suitability_disclaimer') }}</p>
                    <div class="mt-6 flex flex-wrap gap-3"><x-ui.button type="button" wire:click="continueTo('consent')">{{ __('Continue') }}</x-ui.button><x-ui.button type="button" wire:click="back" variant="ghost">{{ __('Back') }}</x-ui.button></div>
                @else
                    <form wire:submit="submit" class="flex flex-col gap-4">
                        <h2 class="font-display text-xl font-semibold text-ink">{{ __('Consent and submit') }}</h2>
                        <label class="flex items-start gap-3 text-sm text-ink"><input type="checkbox" wire:model="consentAssessment" class="mt-0.5 h-4 w-4 rounded border-line-strong text-accent"><span>{{ S::text('consent_assessment') }}</span></label>
                        @error('consentAssessment') <p class="-mt-2 text-xs text-warn">{{ $message }}</p> @enderror
                        <label class="flex items-start gap-3 text-sm text-ink"><input type="checkbox" wire:model="consentContact" class="mt-0.5 h-4 w-4 rounded border-line-strong text-accent"><span>{{ S::text('consent_contact') }}</span></label>
                        @error('consentContact') <p class="-mt-2 text-xs text-warn">{{ $message }}</p> @enderror
                        <p class="rounded-lg bg-surface-2 p-3 text-xs text-ink-muted">{{ __('We will not share your details with any lender until you give separate consent for that lender.') }} {{ S::text('general_disclaimer') }}</p>
                        @error('liabilities') <p class="text-sm text-warn">{{ $message }}</p> @enderror
                        <div class="flex flex-wrap gap-3"><x-ui.button type="submit" wire:loading.attr="disabled">{{ __('Submit my assessment') }}</x-ui.button><x-ui.button type="button" wire:click="back" variant="ghost">{{ __('Back') }}</x-ui.button></div>
                    </form>
                @endif
            @endif
        @elseif ($step === 'done')
            <div class="flex flex-col items-start gap-4" role="status">
                <span class="flex h-12 w-12 items-center justify-center rounded-full bg-pass-soft text-pass" aria-hidden="true"><svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7" /></svg></span>
                <h2 class="font-display text-2xl font-semibold text-ink">{{ __('Your assessment is submitted') }}</h2>
                <p class="text-sm text-ink-muted">{{ __('Reference :reference. A FynnEdge advisor will review it and contact you. You can follow its progress, upload statements and download reports from your profile.', ['reference' => $reference]) }}</p>
                <x-ui.button tag="a" :href="\Illuminate\Support\Facades\Route::has('portal.debt.index') ? route('portal.debt.index') : route('portal.dashboard')">{{ __('Go to my debt consolidation') }}</x-ui.button>
            </div>
        @endif
    </div>
</div>
