<?php

namespace App\Http\Controllers;

use App\Support\Enquiries\PartnerLenders;
use App\Support\Enquiries\QuickEnquiryPageContent;
use Illuminate\Contracts\View\View;

/**
 * The full partner list the Quick Enquiry page's "+N more" link opens. Its
 * heading and intro are edited with the rest of that strip (Website Settings →
 * Quick Enquiry Page); the logos come from Catalog → Lenders.
 */
class PartnerLenderController extends Controller
{
    public function __invoke(): View
    {
        return view('partners', [
            'content' => QuickEnquiryPageContent::resolve(),
            'lenders' => PartnerLenders::all(),
        ]);
    }
}
