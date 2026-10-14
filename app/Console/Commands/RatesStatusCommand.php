<?php

namespace App\Console\Commands;

use App\Enums\LenderStatus;
use App\Enums\RateStatus;
use App\Models\LenderProduct;
use App\Models\LenderProductRate;
use App\Models\LoanProduct;
use App\Support\Rates\RateBook;
use Illuminate\Console\Command;

/**
 * One command an operator can run on a server to answer "why does this loan
 * page show no rate?".
 *
 * A loan page shows a lender's rate only when that rate is published,
 * verified and in date, on an active offer of an active lender. This reports
 * each of those for every loan type, and compares what the database holds
 * with what the site's rate cache is serving, so a missing approval and a
 * stale cache can be told apart.
 */
class RatesStatusCommand extends Command
{
    protected $signature = 'rates:status
                            {product? : A loan product slug, e.g. personal-loan (default: every loan type)}
                            {--refresh : Invalidate the site\'s cached rates so every page re-reads them}';

    protected $description = 'Show, per loan type, which interest rates are live on the site and why the others are not';

    public function handle(): int
    {
        if ($this->option('refresh')) {
            RateBook::flush();
            $this->components->info('Cached rates invalidated — every page now reads the published rates again.');
        }

        // Every published loan type by default; a named one even if it is not
        // published, since "why is this page empty" is often exactly that.
        $products = LoanProduct::query()
            ->when(
                $this->argument('product'),
                fn ($query, string $slug) => $query->where('slug', $slug),
                fn ($query) => $query->published(),
            )
            ->orderedForDisplay()
            ->get();

        if ($products->isEmpty()) {
            $this->components->error("No loan product with the slug \"{$this->argument('product')}\".");

            return self::FAILURE;
        }

        $fresh = RateBook::fresh();
        $database = $fresh->live();
        $site = RateBook::instance()->live();
        $findings = [];

        $rows = $products->map(function (LoanProduct $product) use ($fresh, $database, $site, &$findings): array {
            $activeOffers = LenderProduct::query()
                ->where('loan_product_id', $product->id)
                ->where('status', LenderStatus::Active)
                ->whereHas('lender', fn ($lender) => $lender->where('status', LenderStatus::Active))
                ->count();

            $counts = LenderProductRate::query()
                ->whereHas('lenderProduct', fn ($offer) => $offer->where('loan_product_id', $product->id))
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            $liveInDatabase = $database->where('loanProductId', $product->id)->pluck('lenderProductId')->unique()->count();
            $liveOnSite = $site->where('loanProductId', $product->id)->pluck('lenderProductId')->unique()->count();
            $published = (int) ($counts[RateStatus::Published->value] ?? 0);
            $pending = (int) ($counts[RateStatus::PendingApproval->value] ?? 0);
            $headline = $fresh->headline($product->id);

            if ($liveOnSite !== $liveInDatabase) {
                $findings[] = "{$product->name}: the site's cache shows {$liveOnSite} lenders but the database has {$liveInDatabase} — run `php artisan rates:status --refresh`.";
            }

            if ($pending > 0) {
                $findings[] = "{$product->name}: {$pending} ".str('rate')->plural($pending).' awaiting approval in Catalog → Interest Rates.';
            }

            if ($published > 0 && $liveInDatabase === 0) {
                $findings[] = "{$product->name}: {$published} published, but none live — their offers or lenders are inactive, or their validity dates exclude today.";
            }

            if (! $product->isCurrentlyPublished()) {
                $findings[] = "{$product->name}: the loan product itself is not published, so its page is not public.";
            }

            return [
                "{$product->name} ({$product->slug})",
                $activeOffers,
                $published,
                $pending,
                (int) ($counts[RateStatus::Draft->value] ?? 0),
                $liveInDatabase,
                $liveOnSite,
                $headline?->fromLabel() ?? '—',
            ];
        });

        $this->table(
            ['Loan type', 'Active offers', 'Published', 'Awaiting approval', 'Drafts', 'Live (database)', 'Live (site cache)', 'Headline'],
            $rows->all(),
        );

        if ($findings === []) {
            $this->components->info('Every published, in-date rate is live on the site.');
        }

        foreach ($findings as $finding) {
            $this->components->warn($finding);
        }

        return self::SUCCESS;
    }
}
