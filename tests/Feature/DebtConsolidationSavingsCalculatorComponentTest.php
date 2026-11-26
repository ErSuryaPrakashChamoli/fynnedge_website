<?php

use App\Enums\PublishStatus;
use App\Models\Page;
use App\Models\Setting;
use App\Modules\DebtConsolidation\Services\DebtConsolidationSettings;
use App\Support\Calculators\CalculatorCatalog;
use Livewire\Livewire;

/*
 * Defaults: ₹2 L of cards at 42 % paid ₹10,000 a month (the 5 % minimum due),
 * ₹5 L of loans at 16 % with 36 months left, against 14 % for 3 years with a
 * 2 % fee. Expected figures come from the independent Decimal reference
 * (docs/debt-consolidation/validation/reference.py), never from the engine.
 */

it('is listed with the calculators and served at its own address', function () {
    Setting::set(DebtConsolidationSettings::KEY, ['enabled' => true]);

    expect(collect(CalculatorCatalog::groups())->flatten(1)->pluck('route'))->toContain('calculators.debt-consolidation');

    $this->get(route('calculators.debt-consolidation'))
        ->assertOk()
        ->assertSee('Debt Consolidation Savings Calculator')
        ->assertSeeLivewire('debt-consolidation-savings-calculator');
});

it('stays off the site, its menus and the sitemap until the Debt Consolidation service is live', function () {
    $address = route('calculators.debt-consolidation');

    expect(collect(CalculatorCatalog::groups())->flatten(1)->pluck('route'))->not->toContain('calculators.debt-consolidation');
    $this->get($address)->assertNotFound();
    $this->get(route('calculators.index'))->assertOk()->assertDontSee($address, false);
    $this->get('/sitemap.xml')->assertOk()->assertDontSee($address, false);
});

it('compares the EMI and the total cost, and the breakdown adds up exactly', function () {
    $analysis = Livewire::test('debt-consolidation-savings-calculator')->instance()->analysis;

    expect($analysis['current_emi'])->toBe(27578.52)
        ->and($analysis['new_emi'])->toBe(24502.63)
        ->and($analysis['loan_amount'])->toBe(716920.0)
        ->and($analysis['current_total'])->toBe(982804.48)
        ->and($analysis['new_total'])->toBe(882093.86)
        ->and($analysis['current_interest'])->toBe(282804.48)
        ->and($analysis['new_interest'])->toBe(165174.55)
        ->and($analysis['costs'])->toBe(16919.31)
        ->and($analysis['net_benefit'])->toBe(100710.62)
        ->and($analysis['break_even_month'])->toBe(4)
        ->and(round($analysis['interest_saved'] - $analysis['costs'], 2))->toBe($analysis['net_benefit']);
});

it('finds the best tenure, the best one below today’s EMI and the lowest EMI that still saves', function () {
    $analysis = Livewire::test('debt-consolidation-savings-calculator')->instance()->analysis;
    $byYears = collect($analysis['tenures'])->keyBy('years');

    expect($byYears)->toHaveCount(7)
        ->and($byYears[1]['net'])->toBe(210363.02)
        ->and($byYears[1]['emi'])->toBe(64370.18)
        ->and($byYears[4]['net'])->toBe(42442.08)
        ->and($byYears[4]['emi'])->toBe(19590.9)
        ->and($byYears[5]['net'])->toBe(-18083.42)
        ->and($byYears[7]['net'])->toBe(-145742.2)
        ->and($analysis['best_years'])->toBe(1)
        ->and($analysis['within_today_years'])->toBe(3)
        ->and($analysis['lowest_saving_emi_years'])->toBe(4);
});

it('never calls a lower EMI a saving when the total cost goes up, and points to a tenure that saves', function () {
    Livewire::test('debt-consolidation-savings-calculator')
        ->call('useTenure', 5)
        ->assertSet('newYears', 5)
        ->assertSee('Extra cost')
        ->assertSee('₹18,083')
        ->assertSee('A 3-year loan keeps your EMI below today’s (₹24,503 a month) and saves ₹1,00,711.')
        ->assertDontSee('Less to repay in total over');
});

it('never gives an end date for cards whose payment does not cover the interest', function () {
    // ₹2 L at 42 % accrues ₹7,000 a month, so ₹6,000 a month never clears it.
    Livewire::test('debt-consolidation-savings-calculator')
        ->set('cardPayment', 6000)
        ->assertSeeHtml('data-never-clears')
        ->assertSee('Never at this payment')
        ->assertSee('Keeps growing');
});

it('shows each debt as it stands, exposing what minimum payments cost', function () {
    $debts = collect(Livewire::test('debt-consolidation-savings-calculator')->instance()->analysis['debts'])->keyBy('key');

    expect($debts['cards']['monthly'])->toBe(10000.0)
        ->and($debts['cards']['months'])->toBe(35)
        ->and($debts['cards']['interest'])->toBe(149977.91)
        ->and($debts['loans']['monthly'])->toBe(17578.52)
        ->and($debts['loans']['interest'])->toBe(132826.57);
});

it('starts the card payment at the minimum due and follows the balance until the visitor sets it', function () {
    Livewire::test('debt-consolidation-savings-calculator')
        ->assertSet('cardPayment', 10000.0)
        ->set('cardOutstanding', 300000)
        ->assertSet('cardPayment', 15000.0)
        ->set('cardPayment', 12000)
        ->set('cardOutstanding', 400000)
        ->assertSet('cardPayment', 12000.0);
});

it('shows the share of income going to EMIs once an income is given', function () {
    $analysis = Livewire::test('debt-consolidation-savings-calculator')
        ->set('monthlyIncome', 100000)
        ->instance()->analysis;

    expect($analysis['affordability']['today'])->toBe(27.58)
        ->and($analysis['affordability']['after'])->toBe(24.5);
});

it('says plainly when no tenure saves money', function () {
    // Six months left at 12 %: even a 1-year loan at 14 % plus a fee costs more.
    $component = Livewire::test('debt-consolidation-savings-calculator')
        ->set('cardOutstanding', 0)
        ->set('loanRate', 12)
        ->set('loanMonths', 6);

    expect(collect($component->instance()->analysis['tenures'])->every(fn (array $tenure): bool => $tenure['net'] <= 0))->toBeTrue();
    $component->assertSee('No tenure from 1 to 7 years saves money at 14% with a 2% fee');
});

it('reopens shared figures from the link, keeping each value inside its range', function () {
    Livewire::withQueryParams(['cards' => '300000', 'card_payment' => '20000', 'years' => '15', 'rate' => 'abc', 'income' => '80000'])
        ->test('debt-consolidation-savings-calculator')
        ->assertSet('cardOutstanding', 300000.0)
        ->assertSet('cardPayment', 20000.0)
        ->assertSet('newYears', 7)
        ->assertSet('newRate', 14.0)
        ->assertSet('monthlyIncome', 80000.0);
});

it('builds a share link with the figures entered, leaving out a card payment the visitor never set', function () {
    $link = Livewire::test('debt-consolidation-savings-calculator')->set('loanOutstanding', 600000)->instance()->shareUrl;

    expect($link)->toContain('loans=600000')->toContain('years=3')->not->toContain('card_payment=');
});

it('asks for an amount when nothing is owed', function () {
    Livewire::test('debt-consolidation-savings-calculator')
        ->set('cardOutstanding', 0)
        ->set('loanOutstanding', 0)
        ->assertSee('Enter what you owe on credit cards or loans to compare.');
});

it('keeps every input inside its range, including an emptied one', function (string $property, mixed $typed, int|float $kept) {
    Livewire::test('debt-consolidation-savings-calculator')
        ->set($property, $typed)
        ->assertOk()
        ->assertSet($property, $kept)
        ->assertHasErrors([$property]);
})->with([
    'card balance above the maximum' => ['cardOutstanding', 9_000_000, 5_000_000.0],
    'card rate below the minimum' => ['cardRate', 2, 12.0],
    'new tenure above the maximum' => ['newYears', 15, 7],
    'emptied loan balance' => ['loanOutstanding', '', 0.0],
]);

it('sends visitors to the free assessment while it is live, otherwise to an advisor', function () {
    Livewire::test('debt-consolidation-savings-calculator')
        ->assertSee('Talk to an advisor')
        ->assertDontSee(route('debt-consolidation.assessment'));

    Setting::set(DebtConsolidationSettings::KEY, ['enabled' => true]);
    Page::query()->where('slug', 'debt-consolidation-assessment')->update(['status' => PublishStatus::Published, 'published_at' => now()->subMinute()]);

    Livewire::test('debt-consolidation-savings-calculator')
        ->assertSee('Get a free detailed assessment')
        ->assertSee(route('debt-consolidation.assessment'));
});
