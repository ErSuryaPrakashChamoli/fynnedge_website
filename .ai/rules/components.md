---
paths:
  - 'app/Support/Calculators/**,resources/views/components/⚡*.blade.php'
  - 'app/Support/Calculators/**,resources/views/components/⚡emi-calculator.blade.php'
---

# Components

## Adding a new sellable EMI loan type needs no calculator code changes
⚡emi-calculator.blade.php and LoanCalculatorPreset are fully enum/config-driven — the tab switcher is `LoanCalculatorPreset::supportedCategories()`, which is just every LoanCategory with a complete, published LoanProduct row. To add a new EMI-calculator-backed loan type: (1) add a LoanCategory case + getLabel() branch, (2) add its branch to LoanProductFactory::withCalculatorLimits() (exhaustive match, or tests fatal), (3) seed a real published LoanProduct row with calculator limits filled in (min/max/default amount, tenure_months, interest_rate). Never add a new Livewire component or branch inside ⚡emi-calculator.blade.php for this — that component and CalculatorCatalog (app/Support/Calculators/CalculatorCatalog.php, the header mega-menu + /calculators directory's single source of truth) both just read the enum/DB state.

## EMI calculator's loan-details panel is standalone-page only, driven by LoanProduct
⚡emi-calculator.blade.php's "About this loan" panel (explanation, tenure/rate comparison, eligibility/apply CTAs, related resources, FAQs) only renders when `showLoanDetails` is true (the default). loans/show.blade.php explicitly passes `:show-loan-details="false"` because it already renders an equivalent section itself — don't remove that flag or the content will duplicate there.

The panel's content is deliberately CMS-driven, not hardcoded:
- Explanation copy comes from LoanProduct::calculator_explanation (nullable, separate from the general `body` field so marketing can edit calculator copy independently), falling back to `summary`.
- Tenure/rate comparison tables are fully computed live via EmiCalculator::calculate() (LoanCalculatorPreset's min/mid/max tenure and min/current/max rate) — never hand-maintained copy.
- Related resources come from Article::forCategoryOrGeneral($category) (articles.category is nullable — untagged = general/shown everywhere).
- FAQs and the FAQPage JSON-LD both come from LoanProduct::faqs() (already admin-managed via FaqsRelationManager) — keep reusing that relation rather than adding a parallel FAQ source.
- LoanCalculatorPreset::productFor(LoanCategory) is the public way to resolve the backing LoanProduct for a category; don't duplicate that query elsewhere.

## Quick eligibility calculator CTAs: reuse loans.apply and contact prefill, don't invent per-lender application
⚡loan-eligibility-calculator.blade.php's quick calculator produces plain array $results, not EligibilityResult models — it can't feed applications.select (that route needs a real EligibilityResult from the full JourneyController flow). So its per-lender "Apply Now" CTA just links to route('loans.apply', $loanProductSlug), same as lender-offer-card.blade.php — it re-enters the real journey, which re-evaluates eligibility and lets the user pick a lender there. loanProductSlug/loanProductName are captured in mount() alongside loanProductId to keep Livewire props plain/serializable.

Its "Talk to a FynnEdge Expert" CTA links to route('contact', ['message' => ...]) with lender/loan context baked into the message text. ContactController::show() reads that as $request->string('message') and passes 'prefillMessage' to the view; contact.blade.php passes it via :value to x-ui.textarea, which already supports old($name, $attributes->get('value')). Reuse this same prefill mechanism for any other "talk to an expert with context" CTA rather than adding a new contact entry point.
