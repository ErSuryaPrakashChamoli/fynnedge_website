<?php

use App\Enums\PublishStatus;
use App\Models\Article;
use App\Models\LoanLandingPage;
use App\Models\LoanProduct;
use App\Models\Page;
use App\Models\User;
use App\Support\Pages\DebtConsolidationPages;
use Database\Seeders\ArticleSeeder;
use Database\Seeders\CalculatorLoanProductSeeder;
use Database\Seeders\LegalPageSeeder;
use Illuminate\Support\Facades\Storage;

/*
 * Replays 2026-11-26: customised content exists, then the starter seeders run
 * unattended at 13:09 UTC and overwrite or create content.
 */
function replayUnattendedSeedAt(string $time): void
{
    test()->travelTo($time);
    (new CalculatorLoanProductSeeder)->run();
    (new LegalPageSeeder)->run();
    (new ArticleSeeder)->run();
}

function customisedSite(): array
{
    test()->travelTo('2026-11-01 09:00:00');

    $goldLoan = LoanProduct::factory()->create([
        'slug' => 'gold-loan',
        'summary' => 'Our own gold loan summary.',
        'features' => ['Our own feature'],
        'status' => PublishStatus::Published,
        'published_at' => '2026-10-01 10:00:00',
    ]);
    $privacy = Page::query()->firstOrNew(['slug' => 'privacy-policy']);
    $privacy->fill(['title' => 'Privacy Policy', 'body' => '<p>Our approved privacy policy.</p>', 'status' => PublishStatus::Published, 'published_at' => '2026-09-01 08:00:00'])->save();
    $emiArticle = Article::factory()->create(['slug' => 'how-emis-work', 'title' => 'Our EMI guide', 'status' => PublishStatus::Draft]);
    $travelPage = LoanLandingPage::factory()->create(['slug' => 'personal-loan-for-travel']);

    return [$goldLoan, $privacy->fresh(), $emiArticle, $travelPage];
}

it('restores what an unattended seed overwrote, keeps later and signed-in edits, never touches Debt Consolidation, and hides what it created', function () {
    Storage::fake('local');
    [$goldLoan, $privacy, $emiArticle, $travelPage] = customisedSite();
    $duplicateProduct = LoanProduct::factory()->create(['slug' => 'personal-loan']);
    $debtHub = Page::query()->whereIn('slug', DebtConsolidationPages::slugs())->firstOrFail();
    $debtHubTitle = $debtHub->title;
    $travelPageProductId = $travelPage->loan_product_id;

    replayUnattendedSeedAt('2026-11-26 13:09:08');
    $travelPage->update(['loan_product_id' => $duplicateProduct->id]);
    $debtHub->update(['title' => 'Written in the window']);
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $emiArticle->fresh()->update(['excerpt' => 'Edited by staff during the window.']);
    $this->travelTo('2026-11-27 09:16:00');
    $goldLoan->fresh()->update(['summary' => 'Fixed by hand the next morning.']);
    Article::query()->where('slug', 'what-is-foir')->sole()->update(['title' => 'Kept by staff']);

    $this->artisan('content:restore-from-audit-log', ['--from' => '2026-11-26 13:09:00', '--to' => '2026-11-26 13:09:59', '--apply' => true, '--hide-created' => true])
        ->expectsConfirmation('Restore 4 record(s) and hide 17 created record(s)?', 'yes')
        ->assertSuccessful();

    $goldLoan->refresh();
    expect($goldLoan->summary)->toBe('Fixed by hand the next morning.')
        ->and($goldLoan->features)->toBe(['Our own feature'])
        ->and($goldLoan->published_at->toDateTimeString())->toBe('2026-10-01 10:00:00');
    expect($privacy->fresh())
        ->body->toBe('<p>Our approved privacy policy.</p>')
        ->published_at->toDateTimeString()->toBe('2026-09-01 08:00:00');
    expect($emiArticle->fresh())
        ->title->toBe('Our EMI guide')
        ->status->toBe(PublishStatus::Draft)
        ->excerpt->toBe('Edited by staff during the window.');
    expect($travelPage->fresh()->loan_product_id)->toBe($travelPageProductId);
    expect($debtHub->fresh()->title)->toBe('Written in the window')->not->toBe($debtHubTitle);
    expect(LoanProduct::query()->where('slug', 'term-loan')->sole()->status)->toBe(PublishStatus::Draft);
    expect(Page::query()->where('slug', 'disclaimer')->sole()->status)->toBe(PublishStatus::Draft);
    expect(Article::query()->where('slug', 'what-is-foir')->sole()->status)->toBe(PublishStatus::Published);
    expect(Storage::disk('local')->files('content-restores'))->toHaveCount(1);
});

it('restores the value from before the first run when two unattended runs fall in the window', function () {
    Storage::fake('local');
    [$goldLoan, $privacy] = customisedSite();
    replayUnattendedSeedAt('2026-11-26 13:05:44');
    replayUnattendedSeedAt('2026-11-26 13:09:08');

    $this->artisan('content:restore-from-audit-log', ['--from' => '2026-11-26 13:05:00', '--to' => '2026-11-26 13:09:59', '--apply' => true])
        ->expectsConfirmation('Restore 21 record(s) and hide 0 created record(s)?', 'yes')
        ->assertSuccessful();

    expect($goldLoan->fresh())
        ->summary->toBe('Our own gold loan summary.')
        ->published_at->toDateTimeString()->toBe('2026-10-01 10:00:00');
    expect($privacy->fresh())
        ->body->toBe('<p>Our approved privacy policy.</p>')
        ->published_at->toDateTimeString()->toBe('2026-09-01 08:00:00');
});

it('only previews the restoration unless --apply is given', function () {
    [$goldLoan] = customisedSite();
    replayUnattendedSeedAt('2026-11-26 13:09:08');

    $this->artisan('content:restore-from-audit-log', ['--from' => '2026-11-26 13:09:00', '--to' => '2026-11-26 13:09:59'])
        ->expectsOutputToContain('Preview only: nothing was changed.')
        ->assertSuccessful();

    expect($goldLoan->fresh()->summary)->not->toBe('Our own gold loan summary.');
});

it('changes nothing when the operator declines the confirmation', function () {
    [$goldLoan] = customisedSite();
    replayUnattendedSeedAt('2026-11-26 13:09:08');
    $seededSummary = $goldLoan->fresh()->summary;

    $this->artisan('content:restore-from-audit-log', ['--from' => '2026-11-26 13:09:00', '--to' => '2026-11-26 13:09:59', '--apply' => true])
        ->expectsConfirmation('Restore 3 record(s) and hide 0 created record(s)?', 'no')
        ->assertFailed();

    expect($goldLoan->fresh()->summary)->toBe($seededSummary);
});

it('fails without a complete time window', function () {
    $this->artisan('content:restore-from-audit-log', ['--from' => '2026-11-26 13:09:00'])
        ->expectsOutputToContain('Give the window as --from and --to in UTC')
        ->assertFailed();
});
