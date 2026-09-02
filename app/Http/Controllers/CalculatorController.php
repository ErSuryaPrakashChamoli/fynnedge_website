<?php

namespace App\Http\Controllers;

use App\Enums\LoanCategory;
use App\Models\CalculatorPage;
use App\Models\LoanProduct;
use App\Support\Calculators\CalculatorCatalog;
use App\Support\Calculators\CalculatorPageKey;
use App\Support\Calculators\LoanCalculatorPreset;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;

class CalculatorController extends Controller
{
    public function index(): View
    {
        return view('calculators.index', [
            'groups' => CalculatorCatalog::groups(),
        ]);
    }

    public function emi(string $category): View
    {
        $loanCategory = LoanCategory::tryFrom($category);

        abort_unless($loanCategory && LoanCalculatorPreset::for($loanCategory), 404);

        return view('calculators.emi', [
            'category' => $loanCategory,
        ]);
    }

    public function fixedDeposit(): View
    {
        return view('calculators.fixed-deposit', [
            'calculatorPage' => $this->calculatorPageFor(CalculatorPageKey::FixedDeposit),
            'eligibilityUrl' => $this->generalEligibilityUrl(),
        ]);
    }

    public function sip(): View
    {
        return view('calculators.sip', [
            'calculatorPage' => $this->calculatorPageFor(CalculatorPageKey::Sip),
            'eligibilityUrl' => $this->generalEligibilityUrl(),
        ]);
    }

    public function dailySip(): View
    {
        return view('calculators.daily-sip', [
            'calculatorPage' => $this->calculatorPageFor(CalculatorPageKey::DailySip),
            'eligibilityUrl' => $this->generalEligibilityUrl(),
        ]);
    }

    public function gst(): View
    {
        return view('calculators.gst', [
            'calculatorPage' => $this->calculatorPageFor(CalculatorPageKey::Gst),
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
