<?php

use App\Models\LenderProduct;
use App\Models\LoanProduct;
use App\Models\Page;
use App\Models\Setting;
use App\Models\VideoTestimonial;
use App\Support\Localization\ContentTranslations;
use App\Support\Localization\Phrasebook;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

it('serves the Hindi column while the site runs in Hindi', function () {
    $page = Page::factory()->create(['title' => 'Privacy Policy', 'title_hi' => 'गोपनीयता नीति']);

    expect($page->title)->toBe('Privacy Policy');

    app()->setLocale('hi');

    expect($page->title)->toBe('गोपनीयता नीति');
});

it('falls back to English when a record has no Hindi yet', function () {
    $page = Page::factory()->create(['title' => 'Privacy Policy', 'title_hi' => null, 'excerpt' => 'Short', 'excerpt_hi' => '']);
    app()->setLocale('hi');

    expect($page->title)->toBe('Privacy Policy')
        ->and($page->excerpt)->toBe('Short');
});

it('casts a Hindi json column like its English one', function () {
    $product = LoanProduct::factory()->create(['features' => ['Quick approval'], 'features_hi' => ['त्वरित मंज़ूरी']]);
    app()->setLocale('hi');

    expect($product->fresh()->features)->toBe(['त्वरित मंज़ूरी']);
});

it('keeps English for a query that did not select the Hindi column', function () {
    Page::factory()->create(['title' => 'Privacy Policy', 'title_hi' => 'गोपनीयता नीति']);
    app()->setLocale('hi');

    expect(Page::query()->select(['id', 'title'])->first()->title)->toBe('Privacy Policy');
});

it('backfills Hindi only where the English still matches and no Hindi exists', function () {
    $matching = Page::factory()->create(['title' => 'Privacy Policy']);
    $reworded = Page::factory()->create(['title' => 'Our Privacy Promise']);
    $alreadyTranslated = Page::factory()->create(['title' => 'Privacy Policy', 'title_hi' => 'संपादक का अनुवाद']);
    $product = LoanProduct::factory()->create(['features' => ['Quick approval', 'No collateral']]);

    $directory = storage_path('framework/testing/translations-'.uniqid());
    File::ensureDirectoryExists($directory);
    File::put("{$directory}/pages.json", json_encode([
        ['field' => 'title', 'en' => "Privacy Policy\n", 'hi' => 'गोपनीयता नीति'],
    ]));
    File::put("{$directory}/loan_products.json", json_encode([
        ['field' => 'features', 'en' => ['Quick approval', 'No collateral'], 'hi' => ['त्वरित मंज़ूरी', 'कोई गिरवी नहीं']],
    ]));

    $rateBookVersion = Cache::get('rate-book:version');

    try {
        $written = ContentTranslations::apply('hi', $directory);
    } finally {
        File::deleteDirectory($directory);
    }

    expect(Cache::get('rate-book:version'))->not->toBe($rateBookVersion, 'cached rate rows carry Hindi texts, so a backfill must invalidate them');

    expect($written)->toBe(['loan_products' => 1, 'pages' => 1])
        ->and($matching->fresh()->title_hi)->toBe('गोपनीयता नीति')
        ->and($reworded->fresh()->title_hi)->toBeNull()
        ->and($alreadyTranslated->fresh()->title_hi)->toBe('संपादक का अनुवाद')
        ->and($product->fresh()->features_hi)->toBe(['त्वरित मंज़ूरी', 'कोई गिरवी नहीं']);
});

it('resolves a text setting through the admin Hindi, then the phrase book, then English', function () {
    Setting::set('site_tagline', 'Simplifying Loan, Amplifying Trust');
    Setting::set('footer_disclaimer', 'An admin wording with no Hindi yet');
    app('translator')->addLines(['*.Simplifying Loan, Amplifying Trust' => 'लोन आसान, भरोसा मज़बूत'], 'hi');

    expect(Setting::localized('site_tagline'))->toBe('Simplifying Loan, Amplifying Trust');

    app()->setLocale('hi');

    expect(Setting::localized('site_tagline'))->toBe('लोन आसान, भरोसा मज़बूत')
        ->and(Setting::localized('footer_disclaimer'))->toBe('An admin wording with no Hindi yet');

    Setting::set('site_tagline_hi', 'एडमिन का हिंदी टैगलाइन');

    expect(Setting::localized('site_tagline'))->toBe('एडमिन का हिंदी टैगलाइन');
});

it('takes a page copy field from its Hindi override and translates the rest', function () {
    app('translator')->addLines(['*.Our mission' => 'हमारा मिशन'], 'hi');
    app()->setLocale('hi');

    $content = Phrasebook::localize(
        ['mission_label' => 'Our mission', 'mission_title' => 'Make borrowing simple.', 'values' => [['title' => 'Real ownership']]],
        ['mission_title' => 'उधार लेना आसान बनाएं।', 'values' => [['title' => '']]],
    );

    expect($content)->toBe([
        'mission_label' => 'हमारा मिशन',
        'mission_title' => 'उधार लेना आसान बनाएं।',
        'values' => [['title' => 'Real ownership']],
    ]);
});

it('titles a video testimonial without a headline in the visitor language', function () {
    $testimonial = VideoTestimonial::factory()->make(['customer_name' => 'Asha', 'customer_name_hi' => 'आशा', 'headline' => null]);

    expect($testimonial->playerTitle())->toBe("Asha's story");

    app()->setLocale('hi');

    expect($testimonial->playerTitle())->toBe('आशा की कहानी');
});

it('describes a lender offer fee and eligibility in the visitor language', function () {
    $offer = LenderProduct::factory()->make([
        'processing_fee_flat_amount_min' => null,
        'processing_fee_flat_amount_max' => null,
        'processing_fee_percent_min' => null,
        'processing_fee_percent_max' => 2,
        'processing_fee_gst_extra' => true,
        'min_age' => 21,
        'max_age' => 60,
        'min_monthly_income' => 25000,
        'min_credit_score' => null,
        'min_employment_vintage_months' => null,
        'employment_types' => null,
    ]);

    expect($offer->processingFeeDisplay())->toBe('Up to 2.00% + GST')
        ->and($offer->eligibilitySummaryPoints())->toBe(['Age 21–60 yrs', 'Min. income ₹25,000/mo']);

    app()->setLocale('hi');

    expect($offer->processingFeeDisplay())->toBe('2.00% तक + जीएसटी')
        ->and($offer->eligibilitySummaryPoints())->toBe(['उम्र 21–60 साल', 'न्यूनतम आय ₹25,000/माह']);
});
