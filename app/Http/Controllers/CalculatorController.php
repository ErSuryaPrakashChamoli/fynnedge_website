<?php

namespace App\Http\Controllers;

use App\Enums\LoanCategory;
use App\Models\CalculatorPage;
use App\Models\LoanProduct;
use App\Support\Calculators\CalculatorCatalog;
use App\Support\Calculators\CalculatorIndexing;
use App\Support\Calculators\CalculatorPagesContent;
use App\Support\Calculators\LoanCalculatorPreset;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;

/**
 * Every page's title, headline and introduction is admin-editable (Website
 * Settings → Calculators Page) through CalculatorPagesContent, overridable
 * per page (Content → Calculator Pages) through CalculatorPage, whether
 * search engines may index it through CalculatorIndexing, and its "About"
 * section (Content → Calculator Pages) through CalculatorPage.
 */
class CalculatorController extends Controller
{
    public function index(): View
    {
        return view('calculators.index', [
            'groups' => CalculatorCatalog::groups(),
            'content' => CalculatorPagesContent::forPage('index'),
            'robots' => CalculatorIndexing::robotsFor('index'),
        ]);
    }

    public function emi(string $category): View
    {
        $loanCategory = LoanCategory::tryFrom($category);

        abort_unless($loanCategory && LoanCalculatorPreset::for($loanCategory), 404);

        return view('calculators.emi', [
            'category' => $loanCategory,
            'content' => $this->contentFor('emi', 'emi/'.$loanCategory->value, $loanCategory),
            'robots' => CalculatorIndexing::robotsFor('emi/'.$loanCategory->value),
            'about' => $loanCategory->isHybridRepayment()
                ? $this->aboutFor('emi/'.$loanCategory->value, null, $this->explanationFor(LoanCalculatorPreset::productFor($loanCategory)))
                : null,
        ]);
    }

    public function fixedDeposit(): View
    {
        return view('calculators.fixed-deposit', [
            'about' => $this->aboutFor('fixed-deposit'),
            'content' => $this->contentFor('fixed_deposit', 'fixed-deposit'),
            'robots' => CalculatorIndexing::robotsFor('fixed-deposit'),
            'eligibilityUrl' => $this->generalEligibilityUrl(),
        ]);
    }

    public function sip(): View
    {
        return view('calculators.sip', [
            'about' => $this->aboutFor('sip'),
            'content' => $this->contentFor('sip', 'sip'),
            'robots' => CalculatorIndexing::robotsFor('sip'),
            'eligibilityUrl' => $this->generalEligibilityUrl(),
        ]);
    }

    public function dailySip(): View
    {
        return view('calculators.daily-sip', [
            'about' => $this->aboutFor('daily-sip'),
            'content' => $this->contentFor('daily_sip', 'daily-sip'),
            'robots' => CalculatorIndexing::robotsFor('daily-sip'),
            'eligibilityUrl' => $this->generalEligibilityUrl(),
        ]);
    }

    public function gst(): View
    {
        return view('calculators.gst', [
            'about' => $this->aboutFor('gst'),
            'content' => $this->contentFor('gst', 'gst'),
            'robots' => CalculatorIndexing::robotsFor('gst'),
            'eligibilityUrl' => $this->generalEligibilityUrl(),
        ]);
    }

    public function eligibility(string $category): View
    {
        $loanCategory = LoanCategory::tryFrom($category);

        abort_unless($loanCategory && in_array($loanCategory, [LoanCategory::PersonalLoan, LoanCategory::HomeLoan], true), 404);

        $product = LoanCalculatorPreset::productFor($loanCategory);

        $content = $this->contentFor('eligibility', 'eligibility/'.$loanCategory->value, $loanCategory);

        return view('calculators.eligibility', [
            'category' => $loanCategory,
            'content' => $content,
            'robots' => CalculatorIndexing::robotsFor('eligibility/'.$loanCategory->value),
            'about' => $this->aboutFor('eligibility/'.$loanCategory->value, $content['about_heading'], $this->explanationFor($product)),
            'applyUrl' => ($product && Route::has('loans.apply')) ? route('loans.apply', $product) : null,
        ]);
    }

    public function prepayment(string $category): View
    {
        $loanCategory = LoanCategory::tryFrom($category);

        abort_unless($loanCategory && LoanCalculatorPreset::for($loanCategory), 404);

        $product = LoanCalculatorPreset::productFor($loanCategory);

        $content = $this->contentFor('prepayment', 'prepayment/'.$loanCategory->value, $loanCategory);

        return view('calculators.prepayment', [
            'category' => $loanCategory,
            'content' => $content,
            'robots' => CalculatorIndexing::robotsFor('prepayment/'.$loanCategory->value),
            'about' => $this->aboutFor('prepayment/'.$loanCategory->value, $content['about_heading'], $this->explanationFor($product)),
            'eligibilityUrl' => $this->eligibilityUrlFor($loanCategory),
            'applyUrl' => ($product && Route::has('loans.apply')) ? route('loans.apply', $product) : null,
        ]);
    }

    /**
     * The calculator type's shared copy, overridden field by field with
     * whatever this one page has set in Content → Calculator Pages.
     *
     * @return array<string, string>
     */
    private function contentFor(string $contentKey, string $pageKey, ?LoanCategory $category = null): array
    {
        return CalculatorPage::contentFor($pageKey, CalculatorPagesContent::forPage($contentKey, $category));
    }

    /**
     * The page's "About" heading and body. The heading defaults to "About the
     * {calculator name}" from CalculatorCatalog unless the page has its own
     * admin-editable default (Eligibility/Prepayment's about_heading).
     *
     * @return array{heading: string, body: ?string}
     */
    private function aboutFor(string $pageKey, ?string $defaultHeading = null, ?string $fallbackBody = null): array
    {
        $defaultHeading ??= 'About the '.(CalculatorCatalog::pages()[$pageKey] ?? 'calculator');

        return CalculatorPage::aboutFor($pageKey, $defaultHeading, $fallbackBody);
    }

    /**
     * Mirrors ⚡emi-calculator.blade.php's own "about this loan" fallback:
     * the admin-editable calculator_explanation if set, else the product's
     * plain summary wrapped as a paragraph, else nothing at all.
     */
    private function explanationFor(?LoanProduct $product): ?string
    {
        if (! $product) {
            return null;
        }

        return $product->calculator_explanation ?: ($product->summary ? '<p>'.e($product->summary).'</p>' : null);
    }

    /**
     * Personal/Home Loan get their own dedicated eligibility calculator; every
     * other category falls back to the general eligibility picker, which
     * still lets a visitor check eligibility. Mirrors
     * ⚡emi-calculator.blade.php's eligibilityUrl() computed property.
     */
    private function eligibilityUrlFor(LoanCategory $category): ?string
    {
        if (Route::has('calculators.eligibility') && in_array($category, [LoanCategory::PersonalLoan, LoanCategory::HomeLoan], true)) {
            return route('calculators.eligibility', $category->value);
        }

        return $this->generalEligibilityUrl();
    }

    private function generalEligibilityUrl(): ?string
    {
        return Route::has('eligibility.index') ? route('eligibility.index') : null;
    }
}
