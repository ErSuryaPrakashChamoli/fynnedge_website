<?php

namespace App\Modules\Enquiries\Concerns;

use App\Modules\Enquiries\Enums\EnquiryOutcome;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The reply every public enquiry form gets: JSON for the Alpine forms, a
 * redirect with a flash message for a plain browser POST with JavaScript off.
 *
 * Shared so both forms are equally tight-lipped. Nothing about the stored
 * record travels back — no id, no name, no history — and a reopened enquiry is
 * reported as created, because whether a number is already on file is not
 * something a stranger typing numbers gets to find out.
 */
trait AnswersEnquirySubmissions
{
    protected function respondTo(Request $request, EnquiryOutcome $outcome): JsonResponse|RedirectResponse
    {
        $confirmation = $this->confirmation($outcome);

        if (! $request->expectsJson()) {
            return back()->with([
                'quickEnquiryTitle' => $confirmation['title'],
                'quickEnquiryStatus' => $confirmation['message'],
            ]);
        }

        return response()->json([
            'outcome' => $outcome->isNewRequest()
                ? EnquiryOutcome::Created->value
                : EnquiryOutcome::Duplicate->value,
            ...$confirmation,
        ], $outcome === EnquiryOutcome::Duplicate ? 200 : 201);
    }

    /**
     * @return array{title: string, message: string}
     */
    protected function confirmation(EnquiryOutcome $outcome): array
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
    protected function resolveLandingPage(Request $request): string
    {
        return '/'.ltrim((string) parse_url((string) $request->headers->get('referer', ''), PHP_URL_PATH), '/');
    }
}
