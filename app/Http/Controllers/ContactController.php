<?php

namespace App\Http\Controllers;

use App\Models\ContactEnquiry;
use App\Models\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function show(Request $request): View
    {
        $contactMapUrl = Setting::get('contact_map_url');

        return view('contact', [
            'contactPhone' => Setting::get('contact_phone'),
            'contactEmail' => Setting::get('contact_email'),
            'contactWhatsapp' => Setting::get('contact_whatsapp'),
            'contactAddress' => Setting::get('contact_address'),
            'contactMapUrl' => $contactMapUrl,
            'contactMapViewUrl' => self::mapViewUrl($contactMapUrl),
            'prefillMessage' => $request->string('message')->limit(2000)->toString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:20'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        ContactEnquiry::query()->create([
            ...$data,
            'source_url' => url()->previous(),
            // Set explicitly so this form shows up alongside the Quick Enquiry and
            // loan-page leads in the admin list with a placement of its own, rather
            // than as the one row type with an empty Enquiry Source column.
            'enquiry_source' => 'Contact Page',
        ]);

        return back()->with([
            'statusTitle' => 'Your Loan Query Has Been Submitted Successfully! 🎉',
            'status' => 'Thank you for choosing FynnEdge. Our loan expert will connect with you shortly to understand your requirement and guide you through the next steps.',
        ]);
    }

    /**
     * The embed variant of a `maps?q=...` link fails to load place details when clicked
     * on a bare coordinate pin with no registered Google Business listing — the contact
     * view catches that click with an overlay pointing here instead, to open the same
     * location on the full Google Maps site rather than the broken embedded info window.
     * The dedicated `/maps/embed?pb=...` variant (from Share > Embed a map) has no plain
     * equivalent to link to, so it's left as null and the click passes through untouched.
     */
    private static function mapViewUrl(?string $embedUrl): ?string
    {
        if (! $embedUrl || str_contains($embedUrl, '/maps/embed')) {
            return null;
        }

        $viewUrl = preg_replace('/([?&])output=embed&?/', '$1', $embedUrl);

        return rtrim($viewUrl, '?&');
    }
}
