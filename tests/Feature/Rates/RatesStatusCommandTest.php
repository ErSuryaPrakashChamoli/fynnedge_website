<?php

use App\Enums\LoanCategory;
use App\Enums\RateStatus;
use App\Models\Lender;
use App\Models\LenderProduct;
use App\Models\LenderProductRate;
use App\Models\LoanProduct;
use App\Support\Rates\RateBook;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function statusProduct(): LoanProduct
{
    return LoanProduct::factory()->published()->create(['category' => LoanCategory::PersonalLoan, 'slug' => 'personal-loan', 'name' => 'Personal Loan']);
}

it('reports rates still awaiting approval for a loan type', function () {
    $product = statusProduct();
    publishRate(LenderProduct::factory()->for($product, 'loanProduct')->for(Lender::factory())->create(), 10.25);
    LenderProductRate::factory()->pendingApproval()->count(2)->for(LenderProduct::factory()->for($product, 'loanProduct'), 'lenderProduct')->create();

    Artisan::call('rates:status', ['product' => 'personal-loan']);

    expect(Artisan::output())
        ->toContain('Personal Loan (personal-loan)')
        ->toContain('from 10.25% p.a.')
        ->toContain('Personal Loan: 2 rates awaiting approval in Catalog → Interest Rates.');
});

it('spots a site cache that no longer matches the database and refreshes it', function () {
    $product = statusProduct();
    $offer = LenderProduct::factory()->for($product, 'loanProduct')->create();
    RateBook::headlineFor($product);
    // A rate published without going through the app (e.g. a manual database edit) fires no hook.
    DB::table('lender_product_rates')->insert([
        ...LenderProductRate::factory()->published()->make(['lender_product_id' => $offer->id, 'rate_min' => 9.9])->getAttributes(),
        'public_id' => (string) Str::uuid(),
        'status' => RateStatus::Published->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Artisan::call('rates:status', ['product' => 'personal-loan']);
    expect(Artisan::output())->toContain("the site's cache shows 0 lenders but the database has 1");

    Artisan::call('rates:status', ['product' => 'personal-loan', '--refresh' => true]);
    app()->forgetInstance(RateBook::class);

    expect(RateBook::headlineFor($product)->startingLabel())->toBe('9.90%');
});
