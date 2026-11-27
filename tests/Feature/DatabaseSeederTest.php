<?php

use App\Models\Article;
use App\Models\LoanProduct;
use Illuminate\Support\Facades\Artisan;

/*
 * On 2026-11-26 a bare `php artisan db:seed --force` ran against production and
 * its updateOrCreate calls replaced the live loan products, landing pages, About
 * and legal pages with the starter text, and published ten starter articles.
 */

it('refuses to overwrite existing website content when nobody confirms, and lists what is there', function () {
    $personalLoan = LoanProduct::factory()->create(['slug' => 'personal-loan', 'summary' => 'Our own approved wording.']);
    $this->withoutMockingConsoleOutput();

    expect(fn () => Artisan::call('db:seed', ['--no-interaction' => true]))
        ->toThrow(RuntimeException::class, 'existing website content was left unchanged');

    expect(Artisan::output())->toContain('loan_products');
    expect($personalLoan->fresh()->summary)->toBe('Our own approved wording.');
    expect(Article::query()->count())->toBe(0);
});

it('never overwrites existing website content on production, even with --force', function () {
    $personalLoan = LoanProduct::factory()->create(['slug' => 'personal-loan', 'summary' => 'Our own approved wording.']);
    app()->detectEnvironment(fn (): string => 'production');

    expect(fn () => $this->artisan('db:seed', ['--force' => true])->run())
        ->toThrow(RuntimeException::class, 'never run on production over existing content');

    expect($personalLoan->fresh()->summary)->toBe('Our own approved wording.');
    expect(Article::query()->count())->toBe(0);
});

it('overwrites existing website content only after the operator explicitly confirms', function () {
    $personalLoan = LoanProduct::factory()->create(['slug' => 'personal-loan', 'summary' => 'Our own approved wording.']);

    $this->artisan('db:seed')
        ->expectsConfirmation('Overwrite the existing content listed above with the default starter content?', 'yes')
        ->assertSuccessful();

    expect($personalLoan->fresh()->summary)->not->toBe('Our own approved wording.');
});

it('seeds a freshly migrated database without asking', function () {
    $this->artisan('db:seed', ['--no-interaction' => true])->assertSuccessful();

    expect(LoanProduct::query()->where('slug', 'personal-loan')->exists())->toBeTrue();
    expect(Article::query()->count())->toBe(10);
});
