<?php

namespace App\Http\Controllers;

use App\Enums\EnquiryType;
use App\Models\ContactEnquiry;
use App\Modules\CreditScore\Actions\RequestMobileOtp;
use App\Modules\CreditScore\Actions\VerifyMobileOtp;
use App\Modules\CreditScore\Models\MobileOtpChallenge;
use App\Modules\Enquiries\Actions\RecordEnquiry;
use App\Modules\Enquiries\Concerns\AnswersEnquirySubmissions;
use App\Modules\Enquiries\DataTransferObjects\EnquiryDraft;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The Quick Enquiry endpoint: one mobile number in, one message out.
 *
 * Two steps, because the number is OTP-verified before a lead is written:
 * requestOtp() issues a challenge for the number, store() will not record
 * anything until that challenge has been verified with the code. A lead
 * created here is therefore always a number someone could actually receive a
 * message on, which is the whole point of the box — the team calls it back.
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

        ['challenge' => $challenge, 'code' => $code] = $this->issueChallenge($action, $validated['phone'], $request->ip());

        if (! $request->expectsJson()) {
            return back()->withInput()->with([
                'quickEnquiryChallenge' => $challenge->public_id,
                // No SMS gateway is connected yet, so the code is handed straight
                // back instead of being texted — mirrors the journey's OTP step
                // and the credit-score check's demo mode.
                'quickEnquiryDemoOtp' => $code,
            ]);
        }

        return response()->json([
            'otp_challenge_id' => $challenge->public_id,
            'demo_otp_code' => $code,
        ], 201);
    }

    /**
     * Step two: verify the code, then record the lead. The challenge is matched
     * on the submitted number as well as its id, so a verified challenge for one
     * number can never be used to wave through a different one.
     */
    public function store(Request $request, VerifyMobileOtp $verify, RecordEnquiry $record): JsonResponse|RedirectResponse
    {
        $validated = $this->validatePhone($request, [
            'otp_challenge_id' => ['required', 'string', 'max:64'],
            'otp_code' => ['required', 'digits:6'],
            'source' => ['nullable', 'string', 'max:40'],
        ], [
            'otp_challenge_id.required' => 'Please request a verification code first.',
            'otp_code.required' => 'Please enter the 6-digit code we sent you.',
            'otp_code.digits' => 'Please enter the 6-digit code we sent you.',
        ]);

        $challenge = MobileOtpChallenge::query()
            ->where('public_id', $validated['otp_challenge_id'])
            ->where('mobile_number', $validated['phone'])
            ->first();

        if (! $challenge || ! $verify->handle($challenge, $validated['otp_code'])) {
            throw ValidationException::withMessages([
                'otp_code' => 'That code is incorrect or has expired. You can request a new one.',
            ]);
        }

        $outcome = $record->handle(new EnquiryDraft(
            phone: $validated['phone'],
            type: EnquiryType::QuickEnquiry,
            enquirySource: self::PLACEMENTS[$validated['source'] ?? 'website'] ?? self::PLACEMENTS['website'],
            landingPage: $this->resolveLandingPage($request),
            phoneVerified: true,
        ));

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
            'phone.required' => 'Please enter a valid 10-digit mobile number.',
            'phone.regex' => 'Please enter a valid 10-digit mobile number.',
            ...$extraMessages,
        ]);
    }

    /**
     * RequestMobileOtp raises its per-number rate limit against `mobileNumber`,
     * the field name the credit-score component uses. This form's field is
     * `phone`, and an error keyed to a field that is not on the page would never
     * be shown, so the message is re-raised where the visitor can read it.
     *
     * @return array{challenge: MobileOtpChallenge, code: string}
     */
    private function issueChallenge(RequestMobileOtp $action, string $phone, ?string $ipAddress): array
    {
        try {
            return $action->handle($phone, $ipAddress);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages([
                'phone' => $exception->errors()['mobileNumber'] ?? $exception->getMessage(),
            ]);
        }
    }
}
