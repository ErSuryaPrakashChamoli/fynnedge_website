<?php

namespace App\Http\Controllers;

use App\Enums\LenderStatus;
use App\Models\Faq;
use App\Models\Lender;
use App\Models\LoanProduct;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('home', [
            'loanProducts' => LoanProduct::query()->published()->orderBy('name')->get(),
            'faqs' => Faq::query()->published()->whereNull('faqable_id')->orderBy('sort_order')->limit(4)->get(),
            'lenders' => Lender::query()->where('status', LenderStatus::Active)->orderBy('name')->get(),
        ]);
    }
}
