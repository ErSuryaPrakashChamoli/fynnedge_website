<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use App\Support\Faqs\PageFaqs;
use Illuminate\Contracts\View\View;

class FaqController extends Controller
{
    public function index(): View
    {
        return view('faqs.index', [
            'faqs' => PageFaqs::merge(
                Faq::query()
                    ->published()
                    ->whereNull('faqable_id')
                    ->whereNull('placements')
                    ->orderBy('sort_order')
                    ->get(),
            ),
        ]);
    }
}
