<?php

namespace App\Http\Controllers;

use App\Enums\LenderStatus;
use App\Enums\LoanCategory;
use App\Models\Banner;
use App\Models\Faq;
use App\Models\HowItWorksStep;
use App\Models\Lender;
use App\Models\LoanProduct;
use App\Models\MarketingSection;
use App\Models\Setting;
use App\Models\Testimonial;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('home', [
            'flexiHybridProduct' => LoanProduct::query()->published()->where('category', LoanCategory::FlexiHybridTermLoan)->first(),
            'banners' => Banner::query()->published()->orderBy('sort_order')->get(),
            'loanProducts' => LoanProduct::query()->published()->orderedForDisplay()->get(),
            'lenders' => Lender::query()->where('status', LenderStatus::Active)->orderBy('name')->get(),
            'testimonials' => Testimonial::query()->published()->whereNull('loan_category')->orderBy('sort_order')->get(),
            'faqs' => Faq::query()->published()->whereNull('faqable_id')->orderBy('sort_order')->limit(6)->get(),
            'howItWorksSteps' => HowItWorksStep::query()->published()->orderBy('sort_order')->get(),
            'financeCta' => MarketingSection::forPlacement('home_finance_cta'),
            'emiCta' => MarketingSection::forPlacement('home_emi_cta'),
            'finalCta' => MarketingSection::forPlacement('home_final_cta'),
            'flexiHybridTicker' => MarketingSection::forPlacement('home_flexi_hybrid_ticker'),
            'hero' => [
                'eyebrow' => Setting::get('hero_eyebrow', 'FynnEdge Advisory (OPC) Pvt Ltd'),
                'heading' => Setting::get('hero_heading', 'Simplifying loans.'),
                'headingAccent' => Setting::get('hero_heading_accent', 'Amplifying trust.'),
                'subheading' => Setting::get('hero_subheading', 'FynnEdge connects you with suitable banks and NBFCs for personal loans, home loans, car loans, business loans and loans against property — with clear, upfront eligibility before you apply.'),
            ],
        ]);
    }
}
