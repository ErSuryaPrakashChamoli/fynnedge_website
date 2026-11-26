<?php

use App\Enums\PublishStatus;
use App\Models\Page;
use App\Models\Setting;
use App\Modules\DebtConsolidation\Enums\ApplicantType;
use App\Modules\DebtConsolidation\Models\DebtCalculationSnapshot;
use App\Modules\DebtConsolidation\Services\DebtConsolidationSettings;
use App\Modules\DebtConsolidation\Services\LiabilityRows;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(fn () => Setting::set(DebtConsolidationSettings::KEY, ['enabled' => true]));

function workedExampleCalculator(): Testable
{
    // ₹5 L personal loan at 18 % over 36 months + ₹1.5 L card at 42 % paid ₹15,000 a month,
    // against one option: 12 % over 48 months, 1.5 % fee deducted (see the engine tests).
    return Livewire::test('debt-consolidation-calculator')
        ->set('liabilities.0.outstanding', '500000')
        ->set('liabilities.0.rate', '18')
        ->set('liabilities.0.months', '36')
        ->call('addLiability')
        ->set('liabilities.1.type', 'credit_card_outstanding')
        ->set('liabilities.1.outstanding', '150000')
        ->set('liabilities.1.rate', '42')
        ->set('liabilities.1.emi', '15000')
        ->set('scenarios', [LiabilityRows::blankScenario('PL 12 %', 12, 48, 1.5)]);
}

it('asks for a loan before showing results', function () {
    Livewire::test('debt-consolidation-calculator')->assertSee('Enter the outstanding amount of at least one loan to see your results.');
});

it('shows the consolidation result from the engine, including a tenure warning', function () {
    workedExampleCalculator()
        ->assertSee('Estimated net saving')
        ->assertSee('₹2,220')
        ->assertSee('₹6,61,713')
        ->assertSee('₹17,425')
        ->assertSee('₹15,651 less')
        ->assertSee('You would be in debt 1 year longer than today.');
});

it('works on at most the allowed number of loans, whatever a request sends', function () {
    $rows = array_map(fn (int $index): array => [...LiabilityRows::blank(ApplicantType::Individual), 'outstanding' => '100000', 'rate' => '18', 'months' => '24'], range(1, LiabilityRows::MAX_ROWS + 30));

    $component = Livewire::test('debt-consolidation-calculator')->set('liabilities', $rows);

    expect($component->get('liabilities'))->toHaveCount(LiabilityRows::MAX_ROWS)
        ->and($component->instance()->assessment()->portfolio->liabilityCount)->toBe(LiabilityRows::MAX_ROWS);
});

it('keeps at most the allowed number of options, whatever a request sends', function () {
    $options = array_map(fn (int $index): array => LiabilityRows::blankScenario('Option '.$index, 12, 48, 1.5), range(1, LiabilityRows::MAX_SCENARIOS + 30));

    expect(Livewire::test('debt-consolidation-calculator')->set('scenarios', $options)->get('scenarios'))->toHaveCount(LiabilityRows::MAX_SCENARIOS);
});

it('renders every calculator view', function (string $tab, string $expected) {
    workedExampleCalculator()->call('showTab', $tab)->assertSet('tab', $tab)->assertSee($expected);
})->with([
    ['foreclosure', '₹6,50,000'],
    ['comparison', 'Keep as is'],
    ['affordability', 'Enter your net monthly income'],
    ['break_even', 'Costs recovered by interest saved'],
    ['portfolio', 'Weighted average rate'],
]);

it('shows FOIR once income is entered', function () {
    workedExampleCalculator()->set('finances.net_monthly_income', '100000')->call('showTab', 'affordability')
        ->assertSee('33.08%')
        ->assertSee('17.43%');
});

it('switches to business inputs and debt-service cover', function () {
    Livewire::test('debt-consolidation-calculator')
        ->set('applicantType', 'business')
        ->assertSee('Average monthly revenue')
        ->assertSet('liabilities.0.type', 'unsecured_business_loan');
});

it('hides calculators switched off in settings', function () {
    Setting::set(DebtConsolidationSettings::KEY, ['enabled' => true, 'calculators' => ['consolidation', 'portfolio']]);

    workedExampleCalculator()->call('showTab', 'foreclosure')->assertSet('tab', 'consolidation')->assertDontSee('EMI &amp; savings comparison', false);
});

it('limits how often one visitor can save calculator figures', function () {
    // The hand-off leads to the assessment page, so it must be live.
    Page::query()->where('slug', 'debt-consolidation-assessment')->update(['status' => PublishStatus::Published, 'published_at' => now()->subMinute()]);
    $component = workedExampleCalculator();

    foreach (range(1, 20) as $attempt) {
        $component->call('continueToAssessment');
    }

    $component->call('continueToAssessment')->assertHasErrors('liabilities');

    expect(DebtCalculationSnapshot::query()->count())->toBe(20);
});

it('offers the hand-off to the assessment only while that page can be reached', function () {
    workedExampleCalculator()
        ->assertDontSee('Save these figures and get a free assessment')
        ->call('continueToAssessment')
        ->assertHasErrors('liabilities')
        ->assertNoRedirect();

    expect(DebtCalculationSnapshot::query()->count())->toBe(0);
});

it('tells a visitor what each tab shows and where to start before any loan is entered', function () {
    Livewire::test('debt-consolidation-calculator')
        ->call('showTab', 'break_even')
        ->assertSee('When the cost of consolidating is paid back by the interest you save.')
        ->assertSee('Enter my first loan')
        ->call('showTab', 'foreclosure')
        ->assertSee('What it costs to close each loan today: principal, dues, charges and GST.')
        ->assertDontSee('When the cost of consolidating is paid back by the interest you save.');
});
