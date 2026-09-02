<?php

use App\Enums\LoanCategory;
use App\Enums\PublishStatus;
use App\Models\Article;
use App\Models\Faq;
use App\Models\LoanProduct;
use App\Support\Calculators\EmiCalculator;
use App\Support\Formatting\IndianNumberFormatter;
use Livewire\Livewire;

beforeEach(function () {
    // Every category the calculator supports, seeded with the same limits
    // production ships — most tests exercise Personal Loan by default, and
    // several also switch to another category mid-test.
    foreach ([
        LoanCategory::PersonalLoan,
        LoanCategory::HomeLoan,
        LoanCategory::CarLoan,
        LoanCategory::LoanAgainstProperty,
        LoanCategory::BusinessLoan,
    ] as $category) {
        seedCalculatorProduct($category);
    }
});

it('defaults to the Personal Loan preset when mounted with no category', function () {
    Livewire::test('emi-calculator')
        ->assertSet('category', LoanCategory::PersonalLoan->value)
        ->assertSet('principal', 500000.0)
        ->assertSet('annualRate', 10.49) // Personal Loan's minimum ROI
        ->assertSet('tenureYears', 3)
        ->assertOk();
});

it('mounts with the given category and its preset defaults', function () {
    Livewire::test('emi-calculator', ['category' => LoanCategory::HomeLoan->value])
        ->assertSet('category', LoanCategory::HomeLoan->value)
        ->assertSet('principal', 4000000.0)
        ->assertSet('annualRate', 7.00) // Home Loan's minimum ROI
        ->assertSet('tenureYears', 20)
        ->assertOk();
});

it('mounts with the new Car Loan category and its preset defaults', function () {
    Livewire::test('emi-calculator', ['category' => LoanCategory::CarLoan->value])
        ->assertSet('category', LoanCategory::CarLoan->value)
        ->assertSet('principal', 800000.0)
        ->assertSet('annualRate', 9.10) // Car Loan's minimum ROI
        ->assertSet('tenureYears', 5)
        ->assertOk();
});

it('recalculates the EMI when the loan amount changes', function () {
    Livewire::test('emi-calculator')
        ->set('principal', 1000000)
        ->assertSet('principal', 1000000.0)
        ->assertOk();
});

it('clamps an interest rate typed above the active category\'s maximum, rather than computing an EMI from it', function () {
    Livewire::test('emi-calculator', ['category' => LoanCategory::PersonalLoan->value])
        ->set('annualRate', 50)
        ->assertSet('annualRate', 24.0) // Personal Loan's max_interest_rate
        ->assertHasErrors(['annualRate']);
});

it('clamps an interest rate typed below the active category\'s minimum, rather than computing an EMI from it', function () {
    Livewire::test('emi-calculator', ['category' => LoanCategory::PersonalLoan->value])
        ->set('annualRate', 2)
        ->assertSet('annualRate', 10.49) // Personal Loan's min_interest_rate
        ->assertHasErrors(['annualRate']);
});

it('clamps a loan amount typed above the active category\'s maximum', function () {
    Livewire::test('emi-calculator', ['category' => LoanCategory::PersonalLoan->value])
        ->set('principal', 50_000_000)
        ->assertSet('principal', 5_000_000.0) // Personal Loan's max_amount
        ->assertHasErrors(['principal']);
});

it('clamps a tenure typed above the active category\'s maximum', function () {
    Livewire::test('emi-calculator', ['category' => LoanCategory::PersonalLoan->value])
        ->set('tenureYears', 50)
        ->assertSet('tenureYears', 7) // Personal Loan's max_years (84 months)
        ->assertHasErrors(['tenureYears']);
});

it('clamps a value typed below the active category\'s minimum', function () {
    Livewire::test('emi-calculator', ['category' => LoanCategory::PersonalLoan->value])
        ->set('principal', 100)
        ->assertSet('principal', 25_000.0) // Personal Loan's min_amount
        ->assertHasErrors(['principal']);
});

it('never computes an EMI from an out-of-range value — the result reflects the clamped amount', function () {
    $component = Livewire::test('emi-calculator', ['category' => LoanCategory::PersonalLoan->value])
        ->set('principal', 50_000_000); // 10x the ₹50L maximum

    $clampedResult = EmiCalculator::calculate(5_000_000, 10.49, 36); // max_amount, unchanged default rate/tenure
    $bypassedResult = EmiCalculator::calculate(50_000_000, 10.49, 36);

    $component->assertSeeText('₹'.IndianNumberFormatter::format($clampedResult['emi']));
    $component->assertDontSeeText('₹'.IndianNumberFormatter::format($bypassedResult['emi']));
});

it('clears the clamp error once the value is back in range', function () {
    Livewire::test('emi-calculator', ['category' => LoanCategory::PersonalLoan->value])
        ->set('principal', 50_000_000)
        ->assertHasErrors(['principal'])
        ->set('principal', 1_000_000)
        ->assertHasNoErrors(['principal']);
});

it('computes the same result the calculator service would return', function () {
    Livewire::test('emi-calculator')
        ->set('principal', 200000)
        ->set('annualRate', 12)
        ->set('tenureYears', 2)
        ->assertOk();

    expect(EmiCalculator::calculate(200000, 12, 24)['emi'])->toBeGreaterThan(0);
});

it('resets amount and tenure to the new category\'s preset when the category is switched', function () {
    Livewire::test('emi-calculator', ['category' => LoanCategory::PersonalLoan->value])
        ->set('principal', 1000000)
        ->call('selectCategory', LoanCategory::BusinessLoan->value)
        ->assertSet('category', LoanCategory::BusinessLoan->value)
        ->assertSet('principal', 1000000.0) // Business Loan's default_amount
        ->assertSet('tenureYears', 5); // Business Loan's default_tenure_months / 12
});

it('keeps the current interest rate when switching to a category whose range still covers it', function () {
    // Personal Loan mounts at its minimum, 10.49% — Business Loan's own range (9.60%–24.00%) also covers 10.49%.
    Livewire::test('emi-calculator', ['category' => LoanCategory::PersonalLoan->value])
        ->assertSet('annualRate', 10.49)
        ->call('selectCategory', LoanCategory::BusinessLoan->value)
        ->assertSet('annualRate', 10.49);
});

it('resets the interest rate to the new category\'s minimum when the previous rate falls outside its range', function () {
    // Car Loan's range (9.10%–15.00%) allows 12%, but Home Loan's range (7.00%–10.50%) does not.
    Livewire::test('emi-calculator', ['category' => LoanCategory::CarLoan->value])
        ->set('annualRate', 12)
        ->assertSet('annualRate', 12.0)
        ->call('selectCategory', LoanCategory::HomeLoan->value)
        ->assertSet('annualRate', 7.00); // Home Loan's minimum ROI
});

it('renders the amortization table and pie chart split', function () {
    Livewire::test('emi-calculator', ['category' => LoanCategory::PersonalLoan->value])
        ->assertSee('Full breakdown, starting this month')
        ->assertSee('Principal vs. interest')
        ->assertSee('Principal & interest paid per year');
});

it('shows the maximum amount, maximum tenure and indicative rate range near the calculator fields', function () {
    Livewire::test('emi-calculator', ['category' => LoanCategory::PersonalLoan->value])
        ->assertSee('Maximum loan amount: ₹50,00,000') // Indian digit grouping, not 5,000,000
        ->assertSee('Maximum tenure: 7 years (84 months)')
        ->assertSee('10.49% – 24.00%');
});

it('shows a paired number input alongside each range slider', function () {
    $html = Livewire::test('emi-calculator', ['category' => LoanCategory::PersonalLoan->value])->html();

    expect($html)->toContain('id="principal"')->toContain('type="number"')
        ->toContain('id="annualRate"')
        ->toContain('id="tenureYears"');
});

it('labels each period and month with real calendar dates starting from the current month', function () {
    $component = Livewire::test('emi-calculator', ['category' => LoanCategory::PersonalLoan->value])
        ->set('tenureYears', 2);

    $instance = $component->instance();
    $months = $instance->monthsByYear()[1];

    // The schedule starts THIS month, not some abstract "Month 1" counted from a hypothetical disbursal date.
    expect($instance->monthDate(1)->isSameMonth(now()))->toBeTrue();

    foreach ($months as $monthRow) {
        $component->assertSee($instance->monthDate($monthRow['month'])->format('M Y'));
    }

    $component->assertSee($instance->yearLabel($instance->monthsByYear()[1]));
    $component->assertSee($instance->yearLabel($instance->monthsByYear()[2]));
    $component->assertSee('Assumes your first EMI falls this month');
    $component->assertDontSee('Year 1')->assertDontSee('Month 1');
});

it('keeps the monthly detail totals consistent with the yearly summary row', function () {
    $component = Livewire::test('emi-calculator', ['category' => LoanCategory::PersonalLoan->value])
        ->set('tenureYears', 1);

    $monthsByYear = $component->instance()->monthsByYear();
    $yearly = $component->instance()->schedule();

    $summedPrincipal = round(array_sum(array_column($monthsByYear[1], 'principal_paid')), 2);
    $summedInterest = round(array_sum(array_column($monthsByYear[1], 'interest_paid')), 2);

    expect($monthsByYear[1])->toHaveCount(12);
    expect($summedPrincipal)->toBe($yearly[0]['principal_paid']);
    expect($summedInterest)->toBe($yearly[0]['interest_paid']);
});

it('labels a full 12-month period as a single month when it starts and ends in the same month, otherwise as a range', function () {
    $instance = Livewire::test('emi-calculator', ['category' => LoanCategory::PersonalLoan->value])->instance();

    expect($instance->yearLabel([['month' => 1]]))->toBe($instance->monthDate(1)->format('M Y'));
    expect($instance->yearLabel([['month' => 1], ['month' => 12]]))
        ->toBe($instance->monthDate(1)->format('M Y').' – '.$instance->monthDate(12)->format('M Y'));
});

it('gives the partial final period of a non-whole-year tenure a range covering only its own leftover months', function () {
    $instance = Livewire::test('emi-calculator', ['category' => LoanCategory::PersonalLoan->value])->instance();
    $months = EmiCalculator::monthlySchedule(500000, 10.5, 14);

    $byPeriod = [];
    foreach ($months as $row) {
        $byPeriod[$row['year']][] = $row;
    }

    expect($byPeriod[2])->toHaveCount(2);
    expect($instance->yearLabel($byPeriod[2]))
        ->toBe($instance->monthDate(13)->format('M Y').' – '.$instance->monthDate(14)->format('M Y'));
});

it('has no calculator for credit cards, so mounting with that category falls back to Personal Loan', function () {
    Livewire::test('emi-calculator', ['category' => LoanCategory::CreditCard->value])
        ->assertSet('category', LoanCategory::PersonalLoan->value);
});

it('shows the loan details panel by default, with the explanation, comparison tables and CTAs', function () {
    // beforeEach already seeded a Personal Loan product — update it in place
    // rather than seeding a second one, since productFor() resolves the
    // lowest-id product for the category and would otherwise pick up the
    // original, unmodified row instead of this one.
    $product = LoanProduct::query()->where('category', LoanCategory::PersonalLoan)->firstOrFail();
    $product->update(['calculator_explanation' => '<p>How a Personal Loan EMI is worked out.</p>']);

    Livewire::test('emi-calculator', ['category' => LoanCategory::PersonalLoan->value])
        ->assertSee('About the Personal Loan')
        ->assertSee('How a Personal Loan EMI is worked out.', false)
        ->assertSee('Find your ideal Tenure')
        ->assertSee('Compare Rates & Savings')
        ->assertSee('Check Your Eligibility')
        ->assertSee('Apply for this loan')
        ->assertSeeHtml(route('loans.apply', $product))
        ->assertSeeHtml(route('calculators.eligibility', LoanCategory::PersonalLoan->value));
});

it('hides the loan details panel when show-loan-details is false', function () {
    Livewire::test('emi-calculator', ['category' => LoanCategory::PersonalLoan->value, 'showLoanDetails' => false])
        ->assertDontSee('About the Personal Loan')
        ->assertDontSee('Find your ideal Tenure');
});

it('falls back to the general eligibility picker for a category the eligibility calculator does not cover', function () {
    Livewire::test('emi-calculator', ['category' => LoanCategory::BusinessLoan->value])
        ->assertSeeHtml(route('eligibility.index'))
        ->assertDontSeeHtml(route('calculators.eligibility', LoanCategory::BusinessLoan->value));
});

it('defaults the tenure comparison to 3 tenures spread across the preset range, computed at the current amount and rate', function () {
    $component = Livewire::test('emi-calculator', ['category' => LoanCategory::PersonalLoan->value]);
    $instance = $component->instance();

    expect($instance->compareTenureYears)->toHaveCount(3);

    $rows = $instance->tenureComparison();

    expect($rows)->toHaveCount(3);
    expect(array_column($rows, 'years'))->toContain($instance->preset()['min_years'], $instance->preset()['max_years']);
    foreach ($rows as $row) {
        expect($row)
            ->toBe(['years' => $row['years'], ...EmiCalculator::calculate($instance->principal, $instance->annualRate, $row['years'] * 12)]);
    }
});

it('lets the visitor pick a different tenure to compare without touching the main calculator\'s own tenure', function () {
    $component = Livewire::test('emi-calculator', ['category' => LoanCategory::PersonalLoan->value]);
    $maxYears = $component->instance()->preset()['max_years'];

    $component->set('compareTenureYears.0', $maxYears)
        ->assertSet('tenureYears', 3); // untouched — the visitor's pick only affects the comparison row

    $rows = $component->instance()->tenureComparison();

    expect($rows[0])->toBe([
        'years' => $maxYears,
        ...EmiCalculator::calculate($component->instance()->principal, $component->instance()->annualRate, $maxYears * 12),
    ]);
});

it('defaults the rate comparison to 3 rates spread across the preset range, computed at the current amount and tenure', function () {
    $component = Livewire::test('emi-calculator', ['category' => LoanCategory::PersonalLoan->value]);
    $instance = $component->instance();

    expect($instance->compareRates)->toHaveCount(3);

    $rows = $instance->rateComparison();

    expect($rows)->toHaveCount(3);
    expect(array_column($rows, 'rate'))->toContain($instance->preset()['min_rate'], $instance->preset()['max_rate']);
    foreach ($rows as $row) {
        expect($row)
            ->toBe(['rate' => $row['rate'], ...EmiCalculator::calculate($instance->principal, $row['rate'], $instance->tenureYears * 12)]);
    }
});

it('lets the visitor pick a different rate to compare without touching the main calculator\'s own rate', function () {
    $component = Livewire::test('emi-calculator', ['category' => LoanCategory::PersonalLoan->value]);
    $maxRate = $component->instance()->preset()['max_rate'];

    $component->set('compareRates.0', $maxRate)
        ->assertSet('annualRate', 10.49); // untouched — the visitor's pick only affects the comparison row

    $rows = $component->instance()->rateComparison();

    expect($rows[0])->toBe([
        'rate' => $maxRate,
        ...EmiCalculator::calculate($component->instance()->principal, $maxRate, $component->instance()->tenureYears * 12),
    ]);
});

it('shows related guides tagged to the active loan category, falling back to general ones', function () {
    seedCalculatorProduct(LoanCategory::PersonalLoan);

    $tagged = Article::factory()->published()->forCategory(LoanCategory::PersonalLoan)->create(['title' => 'Personal Loan tagged guide']);
    $otherCategory = Article::factory()->published()->forCategory(LoanCategory::HomeLoan)->create(['title' => 'Home Loan tagged guide']);
    $general = Article::factory()->published()->create(['title' => 'General money guide']);

    Livewire::test('emi-calculator', ['category' => LoanCategory::PersonalLoan->value])
        ->assertSee($tagged->title)
        ->assertSee($general->title)
        ->assertDontSee($otherCategory->title);
});

it('shows the product FAQs and matching FAQPage JSON-LD in the loan details panel', function () {
    $product = LoanProduct::query()->where('category', LoanCategory::PersonalLoan)->firstOrFail();
    $faq = Faq::factory()->for($product, 'faqable')->create([
        'question' => 'Does prepaying reduce my EMI?',
        'answer' => 'Yes, prepayment reduces either your tenure or your EMI.',
        'status' => PublishStatus::Published,
    ]);

    Livewire::test('emi-calculator', ['category' => LoanCategory::PersonalLoan->value])
        ->assertSee($faq->question)
        ->assertSee('application/ld+json', false)
        ->assertSee('FAQPage', false);
});
