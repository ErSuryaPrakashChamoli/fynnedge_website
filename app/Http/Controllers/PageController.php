<?php

namespace App\Http\Controllers;

use App\Models\GrievanceLevel;
use App\Models\Page;
use App\Support\Faqs\PageFaqs;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PageController extends Controller
{
    /**
     * A valid signed URL (generated only from the page's own Edit page in
     * Filament) lets an authorised admin preview a draft or not-yet-scheduled
     * page exactly as it will appear live, without making it publicly
     * reachable by anyone else.
     */
    public function show(Request $request, string $slug): View
    {
        $page = Page::query()->where('slug', $slug)->firstOrFail();

        abort_unless($page->isCurrentlyPublished() || $request->hasValidSignature(), 404);

        return view('pages.show', [
            'page' => $page,
            'faqs' => PageFaqs::merge($page->faqs()->published()->get()),
            'grievanceLevels' => $slug === 'grievance'
                ? GrievanceLevel::query()->published()->orderBy('sort_order')->get()
                : new Collection,
        ]);
    }
}
