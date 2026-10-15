<?php

namespace App\Http\Controllers;

use App\Enums\EnquiryType;
use App\Models\ContactEnquiry;
use App\Modules\Enquiries\Actions\RecordEnquiry;
use App\Modules\Enquiries\Concerns\AnswersEnquirySubmissions;
use App\Modules\Enquiries\DataTransferObjects\EnquiryDraft;
use App\Modules\MobileOtp\Actions\RequestMobileOtp;
use App\Modules\MobileOtp\Actions\VerifyMobileOtp;
use App\Modules\MobileOtp\Enums\MobileOtpPurpose;
use App\Modules\MobileOtp\Enums\MobileOtpStatus;
use App\Modules\MobileOtp\Enums\MobileOtpVerificationResult;
use App\Modules\MobileOtp\Exceptions\MobileOtpThrottled;
use App\Modules\MobileOtp\Models\MobileOtpChallenge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The Quick Enquiry endpoint: one mobile number in, one message out.
 *
 * Two steps, because the number is OTP-verified before a lead is written:
 * requestOtp() texts a code for the number (purpose loan_enquiry), store() will
 * not record anything until that challenge has been verified and redeemed. A
 * lead created here is therefore always a number someone could actually
 * receive a message on, which is the whole point of the box — the team calls
 * it back.
 *
 * store() accepts the code itself (the box's own second step), or no code for
 * a challenge already verified through POST /api/v1/mobile-otp/verify. Either
 * way the challenge is redeemed once, so one verification is one lead.
 *
 * The response body is two strings and an outcome word. No record id, no
 * public_id, no name, no count, nothing about what the team has or has not
 * already done with this number — the reply to a stranger who typed a phone
 * number must not double as a lookup tool for whoever else's number they type.
 *
 * Answers JSON to the Alpine form and a redirect-with-flash to a plain browser
 * POST, so the section still works with JavaScript off — the OTP step is then
 * a second server-rendered form rather than a second page.
 */
class QuickEnquiryController extends Controller
{
    use AnswersEnquirySubmissions;

    /**
     * Where on the site the box was rendered, resolved from a fixed list rather
     * than stored as submitted — `enquiry_source` feeds marketing reporting, and
     * a free-text field posted by the browser would let anyone write into it.
     */
    private const PLACEMENTS = [
        'homepage' => 'Homepage Quick Enquiry',
        'website' => 'Website Quick Enquiry',
    ];

    /**
     * Step one: issue an OTP challenge for the number. Nothing is written to
     * contact_enquiries here — an unverified number never becomes a lead, so a
     * script that only ever posts to this endpoint fills the challenge table and
     * the marketing table stays clean.
     */
    public function requestOtp(Request $request, RequestMobileOtp $action): JsonResponse|RedirectResponse
    {
        $validated = $this->validatePhone($request);

        $challenge = $this->issueChallenge($action, $validated['phone'], $request->ip());

        if (! $request->expectsJson()) {
            return back()->withInput()->with('quickEnquiryChallenge', $challenge->public_id);
        }

        // Never the code: it reaches the visitor by SMS only.
        return response()->json([
            'otp_challenge_id' => $challenge->public_id,
            'expires_in_seconds' => $challenge->expiresInSeconds(),
            'resend_after_seconds' => $challenge->resendAvailableInSeconds(),
        ], 201);
    }

    /**
     * Step two: verify the code, then record the lead. The challenge is matched
     * on the submitted number and purpose as well as its id, so a challenge for
     * one number (or one form) can never be used to wave through another.
     */
    public function store(Request $request, VerifyMobileOtp $verify, RecordEnquiry $record): JsonResponse|RedirectResponse
    {
        $length = MobileOtpChallenge::codeLength();

        $validated = $this->validatePhone($request, [
            'otp_challenge_id' => ['required', 'string', 'max:64'],
            'otp_code' => ['nullable', "digits:{$length}"],
            'source' => ['nullable', 'string', 'max:40'],
        ], [
            'otp_challenge_id.required' => __('Please request a verification code first.'),
            'otp_code.digits' => __('Please enter the :length-digit code we sent you.', ['length' => $length]),
        ]);

        $challenge = MobileOtpChallenge::findForVerification($validated['otp_challenge_id'], $validated['phone'], MobileOtpPurpose::LoanEnquiry);

        if (! $challenge) {
            $this->rejectCode(MobileOtpVerificationResult::NotFound);
        }

        if (filled($validated['otp_code'] ?? null)) {
            $result = $verify->handle($challenge, $validated['otp_code']);

            if (! $result->isVerified()) {
                $this->rejectCode($result);
            }
        } elseif ($challenge->status !== MobileOtpStatus::Verified) {
            throw ValidationException::withMessages(['otp_code' => __('Please enter the :length-digit code we sent you.', ['length' => $length])]);
        }

        // Redeeming the challenge and writing the lead succeed or fail together,
        // and the conditional update inside consumeFor() lets exactly one of two
        // simultaneous submissions through.
        $outcome = DB::transaction(function () use ($challenge, $validated, $request, $record) {
            if (! $challenge->consumeFor($validated['phone'], MobileOtpPurpose::LoanEnquiry)) {
                $this->rejectCode(MobileOtpVerificationResult::AlreadyVerified);
            }

            return $record->handle(new EnquiryDraft(
                phone: $validated['phone'],
                type: EnquiryType::QuickEnquiry,
                enquirySource: self::PLACEMENTS[$validated['source'] ?? 'website'] ?? self::PLACEMENTS['website'],
                landingPage: $this->resolveLandingPage($request),
                phoneVerified: true,
            ));
        });

        return $this->respondTo($request, $outcome);
    }

    /**
     * Both steps judge the number the same way, and both must judge the string
     * that will actually be stored: the visitor may paste "+91 98765-43210", so
     * normalisation runs before validation or the 10-digit rule and the stored
     * value disagree about what is valid.
     *
     * @param  array<string, array<int, string>>  $extraRules
     * @param  array<string, string>  $extraMessages
     * @return array<string, mixed>
     */
    private function validatePhone(Request $request, array $extraRules = [], array $extraMessages = []): array
    {
        $request->merge(['phone' => ContactEnquiry::normalizePhone($request->input('phone'))]);

        return $request->validate([
            // Same rule the journey's OTP step uses: Indian mobile numbers start 6-9.
            'phone' => ['required', 'regex:/^[6-9]\d{9}$/'],
            // Honeypot, as on the newsletter form: a person never fills a hidden field.
            'website' => ['prohibited'],
            ...$extraRules,
        ], [
            'phone.required' => __('Please enter a valid 10-digit mobile number.'),
            'phone.regex' => __('Please enter a valid 10-digit mobile number.'),
            ...$extraMessages,
        ]);
    }

    /**
     * Rate limits, the resend cooldown and a send the gateway refused all come
     * back as an error on `phone` — the field the visitor is looking at — on
     * both the JSON and the no-JavaScript path.
     */
    private function issueChallenge(RequestMobileOtp $action, string $phone, ?string $ipAddress): MobileOtpChallenge
    {
        try {
            $challenge = $action->handle($phone, MobileOtpPurpose::LoanEnquiry, $ipAddress);
        } catch (MobileOtpThrottled $exception) {
            throw ValidationException::withMessages(['phone' => $exception->getMessage()]);
        }

        if ($challenge->status === MobileOtpStatus::Failed) {
            throw ValidationException::withMessages([
                'phone' => __('We could not send a verification code right now. Please try again shortly.'),
            ]);
        }

        return $challenge;
    }

    /**
     * A wrong, expired or unknown code all read the same to the visitor, except
     * a locked-out challenge, which needs a new code rather than another guess.
     */
    private function rejectCode(MobileOtpVerificationResult $result): never
    {
        throw ValidationException::withMessages([
            'otp_code' => $result === MobileOtpVerificationResult::AttemptsExhausted
                ? $result->message()
                : MobileOtpVerificationResult::Incorrect->message(),
        ]);
    }
}
