<?php

namespace App\Http\Requests\Api\V1;

use App\Modules\CreditBureau\Enums\ConsentMethod;
use App\Modules\MobileOtp\Services\MobileNumber;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * POST /api/v1/external/cibil/score and /reports — another system (the LMS)
 * with an API key. It does not know FynnEdge's customer ids, so it sends the
 * person's name, mobile, PAN and gender (the customer is matched or created by
 * MatchCustomerForBureauRequest), and always records the consent it holds:
 * there is no FynnEdge consent or application id to point to. Everything else
 * — PAN, gender, consent fields, fresh pulls, the error envelope — is judged
 * exactly as on the internal API.
 */
class StoreExternalCibilCheckRequest extends StoreCibilCheckRequest
{
    protected const CHANNEL = 'external';

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...Arr::except(parent::rules(), ['customer_id', 'journey_session_id', 'consent.credit_consent_id']),
            'name' => ['required', 'string', 'max:150'],
            'mobile' => ['required', 'string', 'regex:'.MobileNumber::PATTERN],
            'consent.method' => ['required', Rule::in(array_keys(ConsentMethod::employeeRecordable()))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'name.required' => 'Enter the customer\'s full name as on the PAN card.',
            'mobile.required' => 'Enter the customer\'s 10-digit Indian mobile number.',
            'consent.method.required' => 'Record the customer\'s consent: consent.method, evidence_reference, consented_at and attested.',
        ];
    }
}
