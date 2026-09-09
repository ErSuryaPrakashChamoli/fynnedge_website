<?php

namespace App\Http\Controllers;

use App\Enums\LenderStatus;
use App\Enums\LoanCategory;
use App\Models\Achievement;
use App\Models\Article;
use App\Models\Banner;
use App\Models\Faq;
use App\Models\HowItWorksStep;
use App\Models\Lender;
use App\Models\LoanProduct;
use App\Models\MarketingSection;
use App\Models\Setting;
use App\Models\Testimonial;
use App\Support\Calculators\CalculatorCatalog;
use App\Support\Faqs\PageFaqs;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $loanProducts = LoanProduct::query()->published()->orderedForDisplay()->get();
        $lenders = Lender::query()->where('status', LenderStatus::Active)->orderBy('name')->get();

        return view('home', [
            'flexiHybridProduct' => LoanProduct::query()->published()->where('category', LoanCategory::FlexiHybridTermLoan)->first(),
            'achievements' => Achievement::query()->published()->orderBy('sort_order')->get(),
            'heroStats' => $this->derivedHeroStats($lenders->count(), $loanProducts->count()),
            'banners' => Banner::query()->published()->orderBy('sort_order')->get(),
            'loanProducts' => $loanProducts,
            'lenders' => $lenders,
            'testimonials' => Testimonial::query()->published()->whereNull('loan_category')->orderBy('sort_order')->get(),
            'faqs' => PageFaqs::merge(
                Faq::query()->published()->whereNull('faqable_id')->whereNull('placements')->orderBy('sort_order')->limit(6)->get(),
            ),
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

    /**
     * The figures the hero strip falls back to until an admin publishes real
     * Achievement rows.
     *
     * Every one is a live COUNT of published records, never a stated business
     * claim — so this can only ever report something the site genuinely
     * contains. Zero-valued entries are dropped by the component rather than
     * rendered as "0+".
     *
     * @return array<int, array{value: int, label: string}>
     */
    private function derivedHeroStats(int $lenderCount, int $loanProductCount): array
    {
        return [
            ['value' => $lenderCount, 'label' => 'Partner banks & NBFCs'],
            ['value' => $loanProductCount, 'label' => 'Loan products compared'],
            ['value' => array_sum(array_map('count', CalculatorCatalog::groups())), 'label' => 'Free loan calculators'],
            ['value' => Article::query()->published()->count(), 'label' => 'Guides & resources'],
        ];
    }
}
