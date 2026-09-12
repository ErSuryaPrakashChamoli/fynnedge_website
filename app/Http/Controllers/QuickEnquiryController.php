<?php

namespace App\Http\Controllers;

use App\Enums\EnquiryType;
use App\Models\ContactEnquiry;
use App\Modules\Enquiries\Actions\RecordEnquiry;
use App\Modules\Enquiries\Concerns\AnswersEnquirySubmissions;
use App\Modules\Enquiries\DataTransferObjects\EnquiryDraft;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The Quick Enquiry endpoint: one mobile number in, one message out.
 *
 * The response body is two strings and an outcome word. No record id, no
 * public_id, no name, no count, nothing about what the team has or has not
 * already done with this number — the reply to a stranger who typed a phone
 * number must not double as a lookup tool for whoever else's number they type.
 *
 * Answers JSON to the Alpine form and a redirect-with-flash to a plain browser
 * POST, so the section still works with JavaScript off.
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

    public function store(Request $request, RecordEnquiry $record): JsonResponse|RedirectResponse
    {
        /*
         * Normalise before validating, not after: the visitor may paste
         * "+91 98765-43210" and the 10-digit rule has to see the number that
         * will actually be stored, or the two disagree about what is valid.
         */
        $request->merge(['phone' => ContactEnquiry::normalizePhone($request->input('phone'))]);

        $validated = $request->validate([
            // Same rule the journey's OTP step uses: Indian mobile numbers start 6-9.
            'phone' => ['required', 'regex:/^[6-9]\d{9}$/'],
            'source' => ['nullable', 'string', 'max:40'],
            // Honeypot, as on the newsletter form: a person never fills a hidden field.
            'website' => ['prohibited'],
        ], [
            'phone.required' => 'Please enter a valid 10-digit mobile number.',
            'phone.regex' => 'Please enter a valid 10-digit mobile number.',
        ]);

        $outcome = $record->handle(new EnquiryDraft(
            phone: $validated['phone'],
            type: EnquiryType::QuickEnquiry,
            enquirySource: self::PLACEMENTS[$validated['source'] ?? 'website'] ?? self::PLACEMENTS['website'],
            landingPage: $this->resolveLandingPage($request),
        ));

        return $this->respondTo($request, $outcome);
    }
}
