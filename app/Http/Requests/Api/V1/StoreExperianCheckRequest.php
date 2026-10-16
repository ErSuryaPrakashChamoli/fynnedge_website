<?php

namespace App\Http\Requests\Api\V1;

use App\Modules\CreditBureau\DataTransferObjects\CreditConsentInput;
use App\Modules\CreditBureau\Enums\ConsentMethod;
use App\Modules\CreditBureau\Models\CreditConsent;
use App\Modules\CreditBureau\Services\PanNumber;
use App\Modules\Customers\Models\Customer;
use App\Modules\Experian\DataTransferObjects\ExperianCheckRequest;
use App\Modules\Experian\Enums\ExperianProduct;
use App\Modules\Experian\Enums\ExperianRequestType;
use App\Modules\Journey\Models\JourneySession;
use App\Modules\MobileOtp\Services\MobileNumber;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * POST /api/v1/experian/score and /api/v1/experian/reports. The person is always
 * an existing customer (by public id) — the API never creates customers or
 * accepts identity details on their own. Name and mobile default to the
 * customer record; the PAN is required.
 * Permission and customer access are checked by the controller and by
 * RequestExperianCheck, not here.
 */
class StoreExperianCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'pan' => PanNumber::normalize($this->input('pan')),
            'mobile' => filled($this->input('mobile')) ? MobileNumber::normalize($this->input('mobile')) : null,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'uuid', Rule::exists('customers', 'public_id')],
            'journey_session_id' => ['nullable', 'uuid', Rule::exists('journey_sessions', 'public_id')],
            'name' => ['nullable', 'string', 'max:150'],
            'mobile' => ['nullable', 'string', 'regex:'.MobileNumber::PATTERN],
            'pan' => ['required', 'string', 'regex:'.PanNumber::PATTERN],
            'consent' => ['required', 'array'],
            'consent.credit_consent_id' => ['nullable', 'uuid', Rule::exists('credit_consents', 'public_id')],
            'consent.method' => ['required_without:consent.credit_consent_id', 'nullable', Rule::in(array_keys(ConsentMethod::employeeRecordable()))],
            'consent.evidence_reference' => ['required_with:consent.method', 'nullable', 'string', 'min:3', 'max:255'],
            'consent.consented_at' => ['required_with:consent.method', 'nullable', 'date'],
            'consent.attested' => ['required_with:consent.method', 'nullable', 'accepted'],
            'fresh' => ['sometimes', 'boolean'],
            'fresh_reason' => ['required_if_accepted:fresh', 'nullable', 'string', 'min:15', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pan.regex' => 'Enter a valid PAN (five letters, four digits, one letter — e.g. ABCDE1234F).',
            'mobile.regex' => 'Enter a 10-digit Indian mobile number.',
            'consent.method.required_without' => 'Give either consent.credit_consent_id or the consent you are recording (consent.method, evidence_reference, consented_at, attested).',
        ];
    }

    public function customer(): Customer
    {
        return Customer::query()->where('public_id', $this->validated('customer_id'))->firstOrFail();
    }

    public function journeySession(): ?JourneySession
    {
        $id = $this->validated('journey_session_id');

        return $id ? JourneySession::query()->where('public_id', $id)->first() : null;
    }

    public function toCheckRequest(ExperianProduct $product, Customer $customer, ?JourneySession $journeySession): ExperianCheckRequest
    {
        $fresh = (bool) $this->validated('fresh', false);

        return new ExperianCheckRequest(
            customer: $customer,
            product: $product,
            name: (string) ($this->validated('name') ?: $customer->full_name),
            mobile: (string) ($this->validated('mobile') ?: $customer->phone),
            pan: (string) $this->validated('pan'),
            consent: $this->consentInput(),
            type: $fresh ? ExperianRequestType::Fresh : ExperianRequestType::Standard,
            freshReason: $fresh ? $this->validated('fresh_reason') : null,
            journeySession: $journeySession,
            channel: 'api',
        );
    }

    private function consentInput(): CreditConsentInput
    {
        $consentId = $this->validated('consent.credit_consent_id');

        if ($consentId) {
            return CreditConsentInput::existing(CreditConsent::query()->where('public_id', $consentId)->firstOrFail());
        }

        return CreditConsentInput::recordedByEmployee(
            ConsentMethod::from((string) $this->validated('consent.method')),
            $this->validated('consent.evidence_reference'),
            filled($this->validated('consent.consented_at')) ? Carbon::parse($this->validated('consent.consented_at')) : null,
            (bool) $this->validated('consent.attested'),
        );
    }

    /**
     * Same envelope as every other answer from this API: branch on `success`;
     * `errors` keeps Laravel's per-field shape.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => $validator->errors()->first(),
            'error_code' => 'validation_failed',
            'errors' => $validator->errors(),
        ], 422));
    }
}
