<?php

namespace App\Http\Resources\Api\V1;

use App\Modules\MobileOtp\Models\MobileOtpChallenge;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The only shape a challenge ever leaves the server in. Deliberately excludes
 * the code, its hash, the full number, the IP address, the provider reference
 * and anything else from the provider's response.
 *
 * @mixin MobileOtpChallenge
 */
class MobileOtpChallengeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'challenge_id' => $this->public_id,
            'mobile_number_masked' => $this->maskedMobileNumber(),
            'purpose' => $this->purpose?->value,
            'status' => $this->effectiveStatus()->value,
            // What the gateway said about the send request — "accepted" is not "delivered".
            'sms_status' => $this->sms_status->value,
            'expires_at' => $this->expires_at->toIso8601String(),
            'expires_in_seconds' => $this->expiresInSeconds(),
            'resend_after_seconds' => $this->resendAvailableInSeconds(),
            'verified' => $this->isVerified(),
            'verified_at' => $this->verified_at?->toIso8601String(),
        ];
    }
}
