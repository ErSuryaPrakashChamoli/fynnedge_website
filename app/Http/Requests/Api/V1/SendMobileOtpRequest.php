<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ValidatesMobileOtpInput;
use Illuminate\Foundation\Http\FormRequest;

class SendMobileOtpRequest extends FormRequest
{
    use ValidatesMobileOtpInput;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->mobileNumberRules();
    }
}
