<?php

namespace App\Http\Controllers;

use App\Models\ContactEnquiry;
use App\Models\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function show(): View
    {
        return view('contact', [
            'contactPhone' => Setting::get('contact_phone'),
            'contactEmail' => Setting::get('contact_email'),
            'contactWhatsapp' => Setting::get('contact_whatsapp'),
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
        ]);

        return back()->with('status', 'Thanks — we\'ve received your message and will get back to you shortly.');
    }
}
