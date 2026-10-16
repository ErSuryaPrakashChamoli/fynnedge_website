<?php

namespace App\Http\Requests\Api\V1\Concerns;

use App\Modules\MobileOtp\Enums\MobileOtpPurpose;
use App\Modules\MobileOtp\Services\MobileNumber;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * The fields both OTP endpoints share, judged identically: the number is
 * normalised BEFORE the rules run, so "+91 98765-43210" is validated (and
 * stored, and texted) as "9876543210".
 */
trait ValidatesMobileOtpInput
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'country_code' => strtoupper(trim((string) ($this->input('country_code') ?: 'IN'))),
            'mobile_number' => MobileNumber::normalize($this->input('mobile_number')),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function mobileNumberRules(): array
    {
        return [
            'mobile_number' => ['required', 'string', 'regex:'.MobileNumber::PATTERN],
            'country_code' => ['required', 'string', Rule::in(MobileNumber::SUPPORTED_COUNTRIES)],
            'purpose' => ['required', 'string', Rule::enum(MobileOtpPurpose::class)->only(MobileOtpPurpose::apiPurposes())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mobile_number.required' => 'Please enter a valid 10-digit mobile number.',
            'mobile_number.regex' => 'Please enter a valid 10-digit mobile number.',
            'country_code.in' => 'Only Indian mobile numbers (country_code "IN") are supported.',
            'purpose.enum' => 'The purpose is not recognised.',
        ];
    }

    public function mobileNumber(): string
    {
        return $this->validated('mobile_number');
    }

    public function purpose(): MobileOtpPurpose
    {
        return MobileOtpPurpose::from($this->validated('purpose'));
    }

    /**
     * Same envelope as every other answer from these endpoints, so a client
     * branches on `success` alone; `errors` keeps Laravel's per-field shape.
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
