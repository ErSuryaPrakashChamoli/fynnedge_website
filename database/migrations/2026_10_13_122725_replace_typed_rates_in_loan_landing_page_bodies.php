<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * LoanLandingPageSeeder used to bake the product's calculator range into
     * every sub-page body as typed text — ", at indicative rates of
     * 10.49%–24% p.a." — which no rate change could ever update. This swaps
     * exactly that generated phrase for the [rate-range:slug] token, which
     * the page renders from the live, verified rates (App\Support\Rates\RateText).
     * Nothing else in a body is touched, so an admin's own edits survive.
     */
    public function up(): void
    {
        DB::table('loan_landing_pages')
            ->join('loan_products', 'loan_products.id', '=', 'loan_landing_pages.loan_product_id')
            ->where('loan_landing_pages.body', 'like', '%at indicative rates of%')
            ->select(['loan_landing_pages.id', 'loan_landing_pages.body', 'loan_products.slug'])
            // By id, not offset: each update drops the row out of the LIKE
            // filter, and offset paging would then skip a page of rows.
            ->chunkById(200, function ($pages): void {
                foreach ($pages as $page) {
                    $body = preg_replace(
                        '/, at indicative rates of \d+(?:\.\d+)?%–\d+(?:\.\d+)?% p\.a(?=\.)/u',
                        ", [rate-range:{$page->slug}]",
                        $page->body,
                    );

                    if ($body !== null && $body !== $page->body) {
                        DB::table('loan_landing_pages')->where('id', $page->id)->update(['body' => $body]);
                    }
                }
            }, 'loan_landing_pages.id', 'id');
    }

    /**
     * The typed figures were unverified and are not restored.
     */
    public function down(): void {}
};
