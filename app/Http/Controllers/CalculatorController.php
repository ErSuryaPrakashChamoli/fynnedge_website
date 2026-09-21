<?php

namespace App\Http\Controllers;

use App\Enums\LoanCategory;
use App\Models\CalculatorPage;
use App\Models\LoanProduct;
use App\Support\Calculators\CalculatorCatalog;
use App\Support\Calculators\CalculatorPageKey;
use App\Support\Calculators\CalculatorPagesContent;
use App\Support\Calculators\LoanCalculatorPreset;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;

/**
 * Every page's title, headline and introduction is admin-editable (Website
 * Settings → Calculators Page) through CalculatorPagesContent.
 */
class CalculatorController extends Controller
{
    public function index(): View
    {
        return view('calculators.index', [
            'groups' => CalculatorCatalog::groups(),
            'content' => CalculatorPagesContent::forPage('index'),
        ]);
    }

    public function emi(string $category): View
    {
        $loanCategory = LoanCategory::tryFrom($category);

        abort_unless($loanCategory && LoanCalculatorPreset::for($loanCategory), 404);

        return view('calculators.emi', [
            'category' => $loanCategory,
            'content' => CalculatorPagesContent::forPage('emi', $loanCategory),
        ]);
    }

    public function fixedDeposit(): View
    {
        return view('calculators.fixed-deposit', [
            'calculatorPage' => $this->calculatorPageFor(CalculatorPageKey::FixedDeposit),
            'content' => CalculatorPagesContent::forPage('fixed_deposit'),
            'eligibilityUrl' => $this->generalEligibilityUrl(),
        ]);
    }

    public function sip(): View
    {
        return view('calculators.sip', [
            'calculatorPage' => $this->calculatorPageFor(CalculatorPageKey::Sip),
            'content' => CalculatorPagesContent::forPage('sip'),
            'eligibilityUrl' => $this->generalEligibilityUrl(),
        ]);
    }

    public function dailySip(): View
    {
        return view('calculators.daily-sip', [
            'calculatorPage' => $this->calculatorPageFor(CalculatorPageKey::DailySip),
            'content' => CalculatorPagesContent::forPage('daily_sip'),
            'eligibilityUrl' => $this->generalEligibilityUrl(),
        ]);
    }

    public function gst(): View
    {
        return view('calculators.gst', [
            'calculatorPage' => $this->calculatorPageFor(CalculatorPageKey::Gst),
            'content' => CalculatorPagesContent::forPage('gst'),
            'eligibilityUrl' => $this->generalEligibilityUrl(),
        ]);
    }

    public function eligibility(string $category): View
    {
        $loanCategory = LoanCategory::tryFrom($category);

        abort_unless($loanCategory && in_array($loanCategory, [LoanCategory::PersonalLoan, LoanCategory::HomeLoan], true), 404);

        $product = LoanCalculatorPreset::productFor($loanCategory);

        return view('calculators.eligibility', [
            'category' => $loanCategory,
            'content' => CalculatorPagesContent::forPage('eligibility', $loanCategory),
            'explanation' => $this->explanationFor($product),
            'applyUrl' => ($product && Route::has('loans.apply')) ? route('loans.apply', $product) : null,
        ]);
    }

    public function prepayment(string $category): View
    {
        $loanCategory = LoanCategory::tryFrom($category);

        abort_unless($loanCategory && LoanCalculatorPreset::for($loanCategory), 404);

        $product = LoanCalculatorPreset::productFor($loanCategory);

        return view('calculators.prepayment', [
            'category' => $loanCategory,
            'content' => CalculatorPagesContent::forPage('prepayment', $loanCategory),
            'explanation' => $this->explanationFor($product),
            'eligibilityUrl' => $this->eligibilityUrlFor($loanCategory),
            'applyUrl' => ($product && Route::has('loans.apply')) ? route('loans.apply', $product) : null,
        ]);
    }

    private function calculatorPageFor(CalculatorPageKey $key): ?CalculatorPage
    {
        return CalculatorPage::query()->where('calculator_key', $key->value)->first();
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
