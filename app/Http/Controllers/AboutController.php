<?php

namespace App\Http\Controllers;

use App\Models\CompanyPhoto;
use App\Models\Page;
use App\Models\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AboutController extends Controller
{
    /**
     * A valid signed URL (generated only from the page's own Edit page in
     * Filament) lets an authorised admin preview a draft or not-yet-scheduled
     * "about" page exactly as it will appear live, without making it publicly
     * reachable by anyone else.
     */
    public function __invoke(Request $request): View
    {
        $page = Page::query()->where('slug', 'about')->firstOrFail();

        abort_unless($page->isCurrentlyPublished() || $request->hasValidSignature(), 404);
        $founderPhotoPath = Setting::get('founder_photo');

        return view('about', [
            'page' => $page,
            'companyPhotos' => CompanyPhoto::query()->published()->orderBy('sort_order')->get(),
            'founderName' => Setting::get('founder_name'),
            'founderPhotoUrl' => $founderPhotoPath ? Storage::disk('public')->url($founderPhotoPath) : null,
        ]);
    }
}
