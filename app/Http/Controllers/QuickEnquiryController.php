<?php

namespace App\Http\Controllers;

use App\Models\ContactEnquiry;
use App\Modules\Enquiries\Actions\SubmitQuickEnquiry;
use App\Modules\Enquiries\Enums\QuickEnquiryOutcome;
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
    public function store(Request $request, SubmitQuickEnquiry $submit): JsonResponse|RedirectResponse
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

        $outcome = $submit->handle(
            phone: $validated['phone'],
            source: $validated['source'] ?? 'website',
            sourceUrl: $this->resolveSourceUrl($request),
        );

        $confirmation = $this->confirmation($outcome);

        if (! $request->expectsJson()) {
            return back()->with([
                'quickEnquiryTitle' => $confirmation['title'],
                'quickEnquiryStatus' => $confirmation['message'],
            ]);
        }

        return response()->json([
            // Reopened is reported as created: whether this number was in the
            // table before is not the visitor's business.
            'outcome' => $outcome->isNewRequest()
                ? QuickEnquiryOutcome::Created->value
                : QuickEnquiryOutcome::Duplicate->value,
            ...$confirmation,
        ], $outcome === QuickEnquiryOutcome::Duplicate ? 200 : 201);
    }

    /**
     * @return array{title: string, message: string}
     */
    private function confirmation(QuickEnquiryOutcome $outcome): array
    {
        return $outcome->isNewRequest()
            ? [
                'title' => 'Thank You!',
                'message' => 'Your enquiry has been submitted successfully. Our team will contact you shortly.',
            ]
            : [
                'title' => "You're Already Connected",
                'message' => 'We already have your enquiry. Our team will contact you shortly.',
            ];
    }

    /**
     * Stored as a path only — the value comes from a visitor and is displayed in
     * the admin panel, so a full URL from the referer could point anywhere.
     */
    private function resolveSourceUrl(Request $request): string
    {
        return '/'.ltrim((string) parse_url((string) $request->headers->get('referer', ''), PHP_URL_PATH), '/');
    }
}
