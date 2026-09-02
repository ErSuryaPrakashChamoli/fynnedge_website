<?php

use App\Models\LoanProduct;
use App\Support\Media\MediaCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Phase 6.8 performance audit: MediaCatalog previously lazy-loaded each
 * SeoMeta row's polymorphic `seoable` owner individually (one query per
 * row) when building record labels/edit URLs. Eager loading collapses this
 * to a fixed number of queries regardless of how many SEO images exist.
 */
beforeEach(function () {
    Storage::fake('public');
});

it('does not issue one query per SeoMeta record when building the catalog', function () {
    $seedSeoMetaRows = function (int $count) {
        foreach (range(1, $count) as $i) {
            $product = LoanProduct::factory()->create(['slug' => "n1-test-{$i}-{$count}"]);
            $product->seoMeta()->create(['og_image_path' => "seo/item-{$i}-{$count}.jpg"]);
            Storage::disk('public')->put("seo/item-{$i}-{$count}.jpg", 'x');
        }
    };

    $seedSeoMetaRows(2);
    DB::enableQueryLog();
    app(MediaCatalog::class)->all();
    $queryCountAtTwo = count(DB::getQueryLog());
    DB::disableQueryLog();
    DB::flushQueryLog();

    $seedSeoMetaRows(10);

    DB::enableQueryLog();
    app(MediaCatalog::class)->all();
    $queryCountAtTwelve = count(DB::getQueryLog());
    DB::disableQueryLog();

    // If SeoMeta owners were still lazy-loaded one at a time, adding 10 more
    // SeoMeta rows would add ~10 more queries. Eager loading keeps the query
    // count flat regardless of row count.
    expect($queryCountAtTwelve)->toBe($queryCountAtTwo);
});
