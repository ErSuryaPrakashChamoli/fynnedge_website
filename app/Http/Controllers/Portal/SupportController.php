<?php

namespace App\Http\Controllers\Portal;

use App\Models\GrievanceLevel;
use App\Models\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Help & Support. The application has no ticketing module, so this page
 * points to the channels that exist — the contact form (an enquiry staff see
 * under Contact Enquiries), phone, email, WhatsApp — and the published
 * grievance-redressal levels.
 */
class SupportController extends PortalController
{
    public function __invoke(Request $request): View
    {
        return view('portal.support', [
            'customer' => $this->customer($request),
            'contactPhone' => Setting::get('contact_phone'),
            'contactEmail' => Setting::get('contact_email'),
            'contactWhatsapp' => Setting::get('contact_whatsapp'),
            'grievanceLevels' => GrievanceLevel::query()->published()->orderBy('sort_order')->get(),
        ]);
    }
}
