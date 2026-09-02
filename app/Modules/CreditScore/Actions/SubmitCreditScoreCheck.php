<?php

namespace App\Modules\CreditScore\Actions;

use App\Modules\CreditBureau\Enums\CreditCheckStatus;
use App\Modules\CreditScore\Contracts\CreditScoreProvider;
use App\Modules\CreditScore\Enums\BureauName;
use App\Modules\CreditScore\Models\CreditScoreCheck;

class SubmitCreditScoreCheck
{
    public function __construct(private readonly CreditScoreProvider $provider) {}

    /**
     * @param  array{full_name: string, date_of_birth: string, pan_number: string}  $data
     */
    public function handle(array $data, BureauName $bureau, string $mobileNumber, ?string $ipAddress = null): CreditScoreCheck
    {
        $check = CreditScoreCheck::query()->create([
            'bureau' => $bureau,
            'mobile_number' => $mobileNumber,
            'mobile_verified_at' => now(),
            'full_name' => $data['full_name'],
            'date_of_birth' => $data['date_of_birth'],
            'pan_number' => $data['pan_number'],
            'provider' => $this->provider->name(),
            'status' => CreditCheckStatus::Pending,
            'consent_given_at' => now(),
            'ip_address' => $ipAddress,
            'requested_at' => now(),
        ]);

        $result = $this->provider->check($check);

        $check->forceFill([
            'status' => $result->status,
            'score' => $result->score,
            'raw_response' => $result->rawResponse,
            'completed_at' => now(),
        ])->save();

        return $check;
    }
}
