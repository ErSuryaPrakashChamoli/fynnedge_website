<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use Illuminate\Contracts\View\View;

class FaqController extends Controller
{
    public function index(): View
    {
        return view('faqs.index', [
            'faqs' => Faq::query()
                ->published()
                ->whereNull('faqable_id')
                ->orderBy('sort_order')
                ->get(),
        ]);
    }
}
