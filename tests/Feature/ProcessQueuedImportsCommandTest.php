<?php

use App\Filament\Imports\LenderProductImporter;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/*
 * The failure this command exists for: a CSV import is accepted, its jobs are
 * queued, and with no worker running nothing ever reports a problem.
 *
 * Assertions read Artisan::output() rather than chaining
 * expectsOutputToContain(): that matcher runs against each individual write,
 * and a rendered table arrives as one write, so only the first of several
 * substring expectations is ever satisfied.
 */
beforeEach(function () {
    config()->set('queue.default', 'database');

    DB::table('imports')->insert([
        'id' => 1,
        'completed_at' => null,
        'file_name' => 'lender-products.csv',
        'file_path' => 'imports/lender-products.csv',
        'importer' => LenderProductImporter::class,
        'processed_rows' => 0,
        'total_rows' => 351,
        'successful_rows' => 0,
        'user_id' => User::factory()->create()->getKey(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

it('reports a stalled import and its queue backlog without processing anything', function () {
    dispatch(function (): void {
        // Never runs in this test; it only has to sit in the queue.
    });

    expect(Artisan::call('imports:process', ['--check' => true]))->toBe(0);

    expect(Artisan::output())
        ->toContain('lender-products.csv')
        ->toContain('0/351')
        ->toContain('NOT FINISHED')
        ->toContain('1 waiting');

    expect(DB::table('jobs')->count())->toBe(1);
});

it('drains the queued jobs so a stalled import can finish', function () {
    dispatch(function (): void {
        DB::table('imports')->where('id', 1)->update([
            'processed_rows' => 351,
            'successful_rows' => 351,
            'completed_at' => now(),
        ]);
    });

    expect(DB::table('jobs')->count())->toBe(1);

    expect(Artisan::call('imports:process'))->toBe(0);

    expect(DB::table('jobs')->count())->toBe(0);
    expect(DB::table('imports')->where('id', 1)->value('completed_at'))->not->toBeNull();
    expect(Artisan::output())->toContain('351/351');
});

it('says so plainly when an import is unfinished but has no jobs left to run', function () {
    expect(Artisan::call('imports:process'))->toBe(0);

    expect(Artisan::output())
        ->toContain('Nothing is waiting in the queue')
        ->toContain('queue:failed');
});
