<?php

namespace App\Http\Controllers;

use App\Models\LoanLandingPage;
use App\Models\Page;
use App\Modules\DebtConsolidation\Services\DebtConsolidationSettings;
use App\Support\Faqs\PageFaqs;
use App\Support\Pages\DebtConsolidationPageContent;
use App\Support\Pages\DebtConsolidationPages;
use App\Support\Seo\SchemaGraph;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The seven public Debt Consolidation pages. Title, intro, body, SEO and
 * FAQs come from each page's General Pages row; the shared sections from
 * DebtConsolidationPageContent. A signed preview link from the page editor
 * shows a draft (and works while the service is switched off) without
 * making it reachable by anyone else.
 */
class DebtConsolidationPageController extends Controller
{
    /**
     * Existing loan landing pages written for consolidating or refinancing
     * debt (e.g. "Personal Loan for Debt Consolidation"). They are linked from
     * here, never duplicated.
     */
    private const RELATED_TITLE_KEYWORDS = ['debt consolidation', 'balance transfer', 'refinanc'];

    public function __invoke(Request $request, string $pageKey): View
    {
        $isPreview = $request->hasValidSignature();

        abort_unless(DebtConsolidationSettings::enabled() || $isPreview, 404);

        $page = Page::query()->where('slug', DebtConsolidationPages::slug($pageKey))->firstOrFail();

        abort_unless($page->isCurrentlyPublished() || $isPreview, 404);

        $content = DebtConsolidationPageContent::resolve();
        $liveKeys = DebtConsolidationPages::publishedKeys();
        // The Savings Calculator is public only while the service is live; a
        // signed preview while it is off must not link to its 404.
        $savingsCalculatorUrl = DebtConsolidationSettings::enabled() ? route('calculators.debt-consolidation') : null;

        return view('debt-consolidation.show', [
            'page' => $page,
            'pageKey' => $pageKey,
            'content' => $content,
            'faqs' => PageFaqs::merge($page->faqs()->published()->get()),
            'related' => DebtConsolidationPageContent::shows($content, $pageKey, 'related') ? $this->relatedLandingPages() : new Collection,
            'applicantTypes' => DebtConsolidationSettings::availableApplicantTypes(),
            'disclaimer' => DebtConsolidationSettings::text('general_disclaimer'),
            'calculatorDisclaimer' => DebtConsolidationSettings::text('calculator_disclaimer'),
            'liveKeys' => $liveKeys,
            'previewWhileOff' => $isPreview && ! DebtConsolidationSettings::enabled(),
            'savingsCalculatorUrl' => $savingsCalculatorUrl,
            'navigation' => $this->navigation($pageKey, $liveKeys, $savingsCalculatorUrl),
            'schemaNodes' => in_array($pageKey, ['hub', 'personal', 'business'], true)
                ? [SchemaGraph::service($page->getRawOriginal('title'), 'Debt consolidation advisory', route(DebtConsolidationPages::route($pageKey)), $page->seoDescription())]
                : [],
        ]);
    }

    /**
     * @return Collection<int, LoanLandingPage>
     */
    private function relatedLandingPages(): Collection
    {
        return LoanLandingPage::query()
            ->published()
            ->whereHas('loanProduct', fn (Builder $query) => $query->published())
            ->where(function (Builder $query): void {
                foreach (self::RELATED_TITLE_KEYWORDS as $keyword) {
                    $query->orWhere('title', 'like', '%'.$keyword.'%');
                }
            })
            ->with('loanProduct')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit(8)
            ->get();
    }

    /**
     * Links between the Debt Consolidation pages whose rows are live, plus the
     * Savings Calculator under /calculators (whenever the service is live),
     * placed just before the Debt Analyser so a visitor sees the quick estimate
     * and the loan-by-loan tool side by side and never mistakes one for the other.
     *
     * @param  list<string>  $liveKeys
     * @return list<array{label: string, url: string, active: bool, tool: bool}>
     */
    private function navigation(string $current, array $liveKeys, ?string $savingsCalculatorUrl): array
    {
        $labels = [
            'hub' => __('Overview'),
            'personal' => __('Personal'),
            'business' => __('Business'),
            'how_it_works' => __('How it works'),
            'calculator' => __('Debt analyser'),
            'assessment' => __('Free assessment'),
            'faqs' => __('FAQs'),
        ];

        $links = [];

        foreach (DebtConsolidationPages::PAGES as $key => $definition) {
            if ($key === 'calculator' && $savingsCalculatorUrl !== null) {
                $links[] = ['label' => __('Savings calculator'), 'url' => $savingsCalculatorUrl, 'active' => false, 'tool' => true];
            }

            if ($key === $current || in_array($key, $liveKeys, true)) {
                $links[] = ['label' => $labels[$key], 'url' => route($definition['route']), 'active' => $key === $current, 'tool' => false];
            }
        }

        return $links;
    }
}
