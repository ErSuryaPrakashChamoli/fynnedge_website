<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ValidatesMobileOtpInput;
use App\Modules\MobileOtp\Models\MobileOtpChallenge;
use Illuminate\Foundation\Http\FormRequest;

class VerifyMobileOtpRequest extends FormRequest
{
    use ValidatesMobileOtpInput {
        messages as sharedMessages;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'challenge_id' => ['required', 'string', 'uuid'],
            ...$this->mobileNumberRules(),
            // `digits` checks the string as sent, so "012345" keeps its leading zero.
            'otp' => ['required', 'digits:'.$this->otpLength()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->sharedMessages(),
            'challenge_id.required' => 'Please request a verification code first.',
            'challenge_id.uuid' => 'That verification request is not valid.',
            'otp.required' => "Please enter the {$this->otpLength()}-digit code we sent you.",
            'otp.digits' => "Please enter the {$this->otpLength()}-digit code we sent you.",
        ];
    }

    public function otp(): string
    {
        return (string) $this->validated('otp');
    }

    private function otpLength(): int
    {
        return MobileOtpChallenge::codeLength();
    }
}
