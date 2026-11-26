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
use App\Modules\DebtConsolidation\Enums\ApplicantType;
use App\Modules\DebtConsolidation\Services\DebtConsolidationSettings;
use App\Support\Calculators\CalculatorCatalog;
use App\Support\Enquiries\QuickEnquiryPageContent;
use App\Support\Faqs\PageFaqs;
use App\Support\Pages\DebtConsolidationPages;
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
            'latestArticles' => Article::query()->published()->shownOnHome()->newestFirst()->with('author')->limit(3)->get(),
            'howItWorksSteps' => HowItWorksStep::query()->published()->orderBy('sort_order')->get(),
            'financeCta' => MarketingSection::forPlacement('home_finance_cta'),
            'emiCta' => MarketingSection::forPlacement('home_emi_cta'),
            'finalCta' => MarketingSection::forPlacement('home_final_cta'),
            'flexiHybridTicker' => MarketingSection::forPlacement('home_flexi_hybrid_ticker'),
            'debtConsolidation' => $this->debtConsolidationFeature(),
            'quickEnquiryButtonLabel' => QuickEnquiryPageContent::resolve()['home_button_label'],
            'hero' => [
                'eyebrow' => Setting::localized('hero_eyebrow', 'FynnEdge Advisory (OPC) Pvt Ltd'),
                'heading' => Setting::localized('hero_heading', 'Simplifying loans.'),
                'headingAccent' => Setting::localized('hero_heading_accent', 'Amplifying trust.'),
                'subheading' => Setting::localized('hero_subheading', 'FynnEdge connects you with suitable banks and NBFCs for personal loans, home loans, car loans, business loans and loans against property — with clear, upfront eligibility before you apply.'),
            ],
        ]);
    }

    /**
     * The Debt Consolidation feature placed straight under the hero, or null
     * while the service is switched off or its hub page is unpublished. Each
     * button points only at a published page; the wording comes from the
     * home_debt_consolidation marketing section when one is published.
     *
     * @return array{marketing: ?MarketingSection, hubUrl: string, assessmentUrl: ?string, calculatorUrl: ?string, howItWorksUrl: ?string, applicantTypes: list<ApplicantType>}|null
     */
    private function debtConsolidationFeature(): ?array
    {
        if (! DebtConsolidationPages::hubIsLive()) {
            return null;
        }

        $published = DebtConsolidationPages::publishedKeys();
        $urlFor = fn (string $key): ?string => in_array($key, $published, true) ? route(DebtConsolidationPages::route($key)) : null;

        return [
            'marketing' => MarketingSection::forPlacement('home_debt_consolidation'),
            'hubUrl' => route(DebtConsolidationPages::route('hub')),
            'assessmentUrl' => $urlFor('assessment'),
            'calculatorUrl' => $urlFor('calculator'),
            'howItWorksUrl' => $urlFor('how_it_works'),
            'applicantTypes' => DebtConsolidationSettings::availableApplicantTypes(),
        ];
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
            ['value' => $lenderCount, 'label' => __('Partner banks & NBFCs')],
            ['value' => $loanProductCount, 'label' => __('Loan products compared')],
            ['value' => array_sum(array_map('count', CalculatorCatalog::groups())), 'label' => __('Free loan calculators')],
            ['value' => Article::query()->published()->count(), 'label' => __('Guides & resources')],
        ];
    }
}
