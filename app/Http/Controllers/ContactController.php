<?php

namespace App\Http\Controllers;

use App\Models\ContactEnquiry;
use App\Models\Setting;
use App\Modules\MobileOtp\Enums\MobileOtpPurpose;
use App\Modules\MobileOtp\Models\MobileOtpChallenge;
use App\Modules\MobileOtp\Services\MobileNumber;
use App\Support\Contact\OfficeMap;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContactController extends Controller
{
    public function show(Request $request): View
    {
        $contactMapUrl = OfficeMap::embedUrl();

        return view('contact', [
            'contactPhone' => Setting::get('contact_phone'),
            'contactEmail' => Setting::get('contact_email'),
            'contactWhatsapp' => Setting::get('contact_whatsapp'),
            'contactAddress' => Setting::localized('contact_address'),
            'contactMapUrl' => $contactMapUrl,
            'contactMapViewUrl' => OfficeMap::viewUrl($contactMapUrl),
            'prefillMessage' => $request->string('message')->limit(2000)->toString(),
            'otpLength' => MobileOtpChallenge::codeLength(),
        ]);
    }

    /**
     * The phone number is optional, but a number we store is one the visitor has
     * proved they hold: when it is filled in, the page verifies it through the
     * public OTP API (purpose contact_enquiry) and posts the challenge id here,
     * where the verification is checked again and spent on this one enquiry.
     * A blank phone needs no code, so name + email + message still go through.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'phone' => filled($request->input('phone')) ? MobileNumber::normalize($request->input('phone')) : null,
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'regex:'.MobileNumber::PATTERN],
            'otp_challenge_id' => ['nullable', 'string', 'max:64'],
            'message' => ['required', 'string', 'max:2000'],
        ], [
            'phone.regex' => __('Please enter a valid 10-digit mobile number, or leave the phone blank.'),
        ]);

        $challenge = $data['phone']
            ? MobileOtpChallenge::findForVerification($data['otp_challenge_id'] ?? null, $data['phone'], MobileOtpPurpose::ContactEnquiry)
            : null;

        if ($data['phone'] && ! $challenge?->isVerified()) {
            throw ValidationException::withMessages([
                'phone' => __('Please verify this number with the code we text you, or leave the phone blank.'),
            ]);
        }

        DB::transaction(function () use ($challenge, $data): void {
            // One verification, one enquiry: a replayed challenge id finds nothing left to spend.
            if ($challenge && ! $challenge->consumeFor($data['phone'], MobileOtpPurpose::ContactEnquiry)) {
                throw ValidationException::withMessages([
                    'phone' => __('That verification has already been used or has expired. Please verify the number again.'),
                ]);
            }

            ContactEnquiry::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'message' => $data['message'],
                'phone_verified_at' => $challenge ? now() : null,
                'source_url' => url()->previous(),
                // Set explicitly so this form shows up alongside the Quick Enquiry and
                // loan-page leads in the admin list with a placement of its own, rather
                // than as the one row type with an empty Enquiry Source column.
                'enquiry_source' => 'Contact Page',
            ]);
        });

        return back()->with([
            'statusTitle' => __('Your Loan Query Has Been Submitted Successfully! 🎉'),
            'status' => __('Thank you for choosing FynnEdge. Our loan expert will connect with you shortly to understand your requirement and guide you through the next steps.'),
        ]);
    }
}
