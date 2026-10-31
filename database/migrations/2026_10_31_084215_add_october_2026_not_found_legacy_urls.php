<?php

use App\Models\Redirect;
use Illuminate\Database\Migrations\Migration;

/**
 * The 32 URLs of the Search Console "Not found (404)" report (October 2026,
 * docs/seo/gsc-not-found-audit-2026-10.csv). All are pages of the old
 * WordPress site that the Laravel rebuild never carried over; none is linked
 * from the current site or listed in its sitemap.
 *
 * Each destination was checked on the live site on 2026-10-31: it answers 200
 * in one hop, its canonical is itself and it is indexable. They use the LIVE
 * slugs (/loans/personal-loans), which differ from the local seed data.
 *
 *   - REDIRECTS: the old page has a page on this site about the same subject.
 *     The five article rows point at the live page on the article's topic;
 *     the business approved those as equivalents.
 *   - GONE: retired blog articles with no equivalent page, plus a WordPress
 *     comment feed and date archive, which cannot exist on this site. They
 *     answer 410, so Google drops them sooner than a 404.
 *
 * The rest of the report (/mutual-fund, /debit-card, /review, /lander) has no
 * row on purpose: those keep answering 404.
 *
 * A source that already has a row, active or not, is left alone: that row is
 * an admin's decision and wins (same rule as the 2026_10_30 legacy migration).
 */
return new class extends Migration
{
    private const NOTE = 'Search Console "Not found (404)" report, Oct 2026: ';

    /**
     * @var array<string, array{0: string, 1: string}> source path => [destination, what the old page was]
     */
    private const REDIRECTS = [
        '/personal-loan' => ['/loans/personal-loans', 'old WordPress personal loan page'],
        '/home-loan' => ['/loans/home-loan', 'old WordPress home loan page'],
        '/business-loan' => ['/loans/business-loan', 'old WordPress business loan page'],
        '/overdraft-facilities' => ['/loans/flexi-hybrid-term-loan', 'old WordPress overdraft page; Flexi Hybrid Term Loan is the overdraft facility'],
        '/contact-us' => ['/contact', 'old WordPress contact page'],
        '/career' => ['/careers', 'old WordPress careers page'],
        '/team' => ['/about', 'old WordPress team page; the team now appears on About'],
        '/gallery' => ['/about', 'old WordPress photo gallery; the Life at FynnEdge photos are on About'],
        '/personal-loan-calculator' => ['/calculators/emi/personal-loan', 'old WordPress personal loan calculator'],
        '/personal-loan-calculator-2' => ['/calculators/emi/personal-loan', 'duplicate WordPress slug of the personal loan calculator'],
        '/car-loan-calculator-2' => ['/calculators/emi/car-loan', 'duplicate WordPress slug of the car loan calculator'],
        '/cibil-score' => ['/credit-score/cibil', 'old WordPress CIBIL score page'],
        '/tips-for-using-a-personal-loan-for-your-dream-holiday' => ['/loans/personal-loans/personal-loan-for-travel', 'retired article; topic page approved as equivalent'],
        '/how-to-combine-multiple-loans-and-reduce-your-emi-burden-in-india-in-2026' => ['/loans/personal-loans/personal-loan-for-debt-consolidation', 'retired article; topic page approved as equivalent'],
        '/are-self-employed-people-eligible-for-a-personal-loan-without-an-itr' => ['/loans/personal-loans/personal-loan-for-self-employed', 'retired article; topic page approved as equivalent'],
        '/best-personal-loan-in-india-2026-a-definitive-ranking-of-top-banks-and-nbfcs' => ['/loans/personal-loans', 'retired article; the page compares banks and NBFCs side by side'],
        '/unsecured-business-loans-for-startups-a-2026-guide-to-capital-without-collateral' => ['/loans/business-loan', 'retired article; the page covers collateral-free business loans'],
    ];

    /**
     * @var array<string, string> source path => what the old page was
     */
    private const GONE = [
        '/the-home-loan-advantage' => 'retired WordPress article, no equivalent page',
        '/taking-out-a-personal-loan-on-probation-is-it-even-an-option' => 'retired WordPress article, no equivalent page',
        '/the-personal-loan-master-guide' => 'retired WordPress article, no equivalent page',
        '/the-msme-growth-manual' => 'retired WordPress article, no equivalent page',
        '/the-credit-score-recovery-roadmap' => 'retired WordPress article, no equivalent page',
        '/how-loan-prepayment-can-save-you-lakhs-in-interest-in-india-in-2026' => 'retired WordPress article, no equivalent page',
        '/how-to-manage-your-emis-smartly-in-2026-and-stay-financially-stress-free' => 'retired WordPress article, no equivalent page',
        '/comments/feed' => 'WordPress comment feed; this site has no comments',
        '/2026/03/03' => 'WordPress date archive; this site has no date archives',
    ];

    public function up(): void
    {
        foreach (self::REDIRECTS as $source => [$destination, $note]) {
            Redirect::query()->firstOrCreate(
                ['source_path' => $source],
                ['destination' => $destination, 'status_code' => Redirect::PERMANENT, 'is_active' => true, 'notes' => self::NOTE.$note],
            );
        }

        foreach (self::GONE as $source => $note) {
            Redirect::query()->firstOrCreate(
                ['source_path' => $source],
                ['destination' => null, 'status_code' => Redirect::GONE, 'is_active' => true, 'notes' => self::NOTE.$note],
            );
        }
    }

    /**
     * Removes only rows still exactly as this migration created them, so an
     * admin's edit to one of them survives a rollback.
     */
    public function down(): void
    {
        foreach (self::REDIRECTS as $source => [$destination]) {
            Redirect::query()
                ->where('source_path', $source)
                ->where('destination', $destination)
                ->where('status_code', Redirect::PERMANENT)
                ->delete();
        }

        foreach (array_keys(self::GONE) as $source) {
            Redirect::query()
                ->where('source_path', $source)
                ->whereNull('destination')
                ->where('status_code', Redirect::GONE)
                ->delete();
        }

        Redirect::forgetCache();
    }
};
