<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SendMobileOtpRequest;
use App\Http\Requests\Api\V1\VerifyMobileOtpRequest;
use App\Http\Resources\Api\V1\MobileOtpChallengeResource;
use App\Modules\MobileOtp\Actions\RequestMobileOtp;
use App\Modules\MobileOtp\Actions\VerifyMobileOtp;
use App\Modules\MobileOtp\Enums\MobileOtpStatus;
use App\Modules\MobileOtp\Enums\MobileOtpVerificationResult;
use App\Modules\MobileOtp\Exceptions\MobileOtpThrottled;
use App\Modules\MobileOtp\Models\MobileOtpChallenge;
use Illuminate\Http\JsonResponse;

/**
 * Public, stateless OTP endpoints (routes/api.php, no session, no auth).
 *
 * Every answer has the envelope { success, message, data | error_code }. The
 * code itself, the API key and the provider's raw response never appear in
 * one. A verified challenge is redeemed exactly once by the protected action
 * it was issued for (see docs/mobile-otp-integration.md) — verifying here does
 * not by itself create a lead or an application.
 */
class MobileOtpController extends Controller
{
    public function send(SendMobileOtpRequest $request, RequestMobileOtp $action): JsonResponse
    {
        try {
            $challenge = $action->handle($request->mobileNumber(), $request->purpose(), $request->ip());
        } catch (MobileOtpThrottled $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'error_code' => $exception->reason,
                'data' => ['retry_after_seconds' => $exception->retryAfterSeconds],
            ], 429, ['Retry-After' => (string) $exception->retryAfterSeconds]);
        }

        if ($challenge->status === MobileOtpStatus::Failed) {
            return response()->json([
                'success' => false,
                'message' => 'We could not send a verification code right now. Please try again shortly.',
                'error_code' => 'sms_not_sent',
            ], 503);
        }

        return MobileOtpChallengeResource::make($challenge)
            ->additional([
                'success' => true,
                'message' => 'Verification code sent if delivery was accepted.',
            ])
            ->response()
            ->setStatusCode(201);
    }

    public function verify(VerifyMobileOtpRequest $request, VerifyMobileOtp $action): JsonResponse
    {
        $challenge = MobileOtpChallenge::findForVerification(
            $request->validated('challenge_id'),
            $request->mobileNumber(),
            $request->purpose(),
        );

        $result = $challenge ? $action->handle($challenge, $request->otp()) : MobileOtpVerificationResult::NotFound;

        if (! $result->isVerified()) {
            return response()->json([
                'success' => false,
                'message' => $result->message(),
                'error_code' => $result->errorCode(),
                // Only once the caller has proved they hold the id, number and purpose.
                ...($result === MobileOtpVerificationResult::Incorrect
                    ? ['data' => ['attempts_remaining' => $challenge->attemptsRemaining()]]
                    : []),
            ], 422);
        }

        return MobileOtpChallengeResource::make($challenge)
            ->additional([
                'success' => true,
                'message' => $result->message(),
            ])
            ->response();
    }
}
