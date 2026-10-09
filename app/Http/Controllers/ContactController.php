<?php

namespace App\Http\Controllers;

use App\Models\ContactEnquiry;
use App\Models\Setting;
use App\Support\Contact\OfficeMap;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function show(Request $request): View
    {
        $contactMapUrl = OfficeMap::embedUrl();

        return view('contact', [
            'contactPhone' => Setting::get('contact_phone'),
            'contactEmail' => Setting::get('contact_email'),
            'contactWhatsapp' => Setting::get('contact_whatsapp'),
            'contactAddress' => Setting::get('contact_address'),
            'contactMapUrl' => $contactMapUrl,
            'contactMapViewUrl' => OfficeMap::viewUrl($contactMapUrl),
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
}
