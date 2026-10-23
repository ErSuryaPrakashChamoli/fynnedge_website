<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use App\Models\Page;
use App\Support\Faqs\PageFaqs;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    /**
     * The heading, intro and SEO come from the `faqs` row under Content →
     * Pages, so like /about this 404s unless that row is published — or the
     * URL carries a valid signature from its Preview action in Filament.
     *
     * The questions are the General FAQs, then any added on that page's own
     * FAQs tab, then any pinned here under Page FAQs.
     */
    public function index(Request $request): View
    {
        $page = Page::query()->where('slug', 'faqs')->firstOrFail();

        abort_unless($page->isCurrentlyPublished() || $request->hasValidSignature(), 404);

        return view('faqs.index', [
            'page' => $page,
            'faqs' => PageFaqs::merge(
                Faq::query()
                    ->published()
                    ->whereNull('faqable_id')
                    ->whereNull('placements')
                    ->orderBy('sort_order')
                    ->get()
                    ->concat($page->faqs()->published()->get()),
            ),
        ]);
    }
}
