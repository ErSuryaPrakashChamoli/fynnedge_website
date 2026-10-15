<?php

use App\Jobs\TranslateContentToHindi;
use App\Jobs\TranslateSettingToHindi;
use App\Models\Lender;
use App\Models\LoanProduct;
use App\Models\Page;
use App\Models\Setting;
use App\Support\Localization\MachineTranslator;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

const GOOGLE_TRANSLATE = 'translation.googleapis.com/*';

/**
 * A stand-in for Google: "translates" by tagging each text, returning the
 * markup (including translate="no" fences) exactly as sent.
 */
function fakeGoogleTranslate(?callable $translate = null): void
{
    $translate ??= fn (string $text): string => 'हिं '.$text;

    Http::fake([GOOGLE_TRANSLATE => fn (Request $request) => Http::response([
        'data' => ['translations' => array_map(fn (string $text): array => ['translatedText' => $translate($text)], $request['q'])],
    ])]);
}

function finishRequest(): void
{
    app(DeferredCallbackCollection::class)->invoke();
}

beforeEach(function () {
    config(['services.google_translate.key' => 'test-key']);
    Http::preventStrayRequests();
});

describe('the translator', function () {
    it('sends HTML to Google with brand and lender names fenced off, then unwraps them', function () {
        fakeGoogleTranslate();
        Lender::factory()->create(['name' => 'HDFC Bank', 'description' => null]);

        $hindi = app(MachineTranslator::class)->translateStrings(['Apply at HDFC Bank via FynnEdge & save'])[0];

        expect($hindi)->toBe('हिं Apply at HDFC Bank via FynnEdge & save');
        Http::assertSent(fn (Request $request) => $request['format'] === 'html'
            && $request['target'] === 'hi'
            && str_contains($request['q'][0], '<span translate="no">HDFC Bank</span>')
            && str_contains($request['q'][0], '<span translate="no">FynnEdge</span>')
            && str_contains($request['q'][0], '&amp; save'));
    });

    it('keeps HTML markup, line breaks and content tokens intact', function () {
        fakeGoogleTranslate();

        [$html, $text] = app(MachineTranslator::class)->translateStrings([
            '<p class="lead">Rates [rate:personal-loan].</p>',
            "Line one\nLine two",
        ]);

        expect($html)->toBe('हिं <p class="lead">Rates [rate:personal-loan].</p>')
            ->and($text)->toBe("हिं Line one\nLine two");
    });

    it('pulls Google\'s formal words back to the site glossary', function () {
        fakeGoogleTranslate(fn () => 'ऋणदाता से ऋण लें, ऋणी बनें');

        expect(app(MachineTranslator::class)->translateStrings(['Borrow from a lender'])[0])
            ->toBe('लेंडर से लोन लें, ऋणी बनें');
    });

    it('reuses the site\'s own Hindi for text it already translates, without calling Google', function () {
        fakeGoogleTranslate();
        app('translator')->addLines(['*.Check Eligibility' => 'पात्रता जांचें'], 'hi');

        expect(app(MachineTranslator::class)->translateStrings(['Check Eligibility'])[0])->toBe('पात्रता जांचें');
        Http::assertNothingSent();
    });

    it('translates only the words in a list of label/value choices', function () {
        fakeGoogleTranslate();

        $hindi = app(MachineTranslator::class)->translateValues([
            'options' => [['value' => 'gig', 'label' => 'Gig worker'], ['value' => 'farmer', 'label' => 'Farm owner']],
        ]);

        expect($hindi['options'])->toBe([['value' => 'gig', 'label' => 'हिं Gig worker'], ['value' => 'farmer', 'label' => 'हिं Farm owner']]);
    });
});

describe('saving content', function () {
    it('queues Hindi for new English content', function () {
        Queue::fake();

        $page = Page::factory()->create(['title' => 'Refund policy', 'title_hi' => null]);

        Queue::assertPushed(TranslateContentToHindi::class, fn (TranslateContentToHindi $job) => $job->modelKey === $page->id && array_key_exists('title', $job->fields));
    });

    it('queues nothing while no Google key is configured', function () {
        config(['services.google_translate.key' => null]);
        Queue::fake();

        Page::factory()->create(['title' => 'Refund policy']);

        Queue::assertNotPushed(TranslateContentToHindi::class);
    });

    it('fills the Hindi columns, and lists stay lists', function () {
        fakeGoogleTranslate();

        $product = LoanProduct::factory()->create(['summary' => 'Quick funds', 'summary_hi' => null, 'features' => ['No collateral'], 'features_hi' => null]);

        expect($product->fresh()->summary_hi)->toBe('हिं Quick funds')
            ->and($product->fresh()->features_hi)->toBe(['हिं No collateral']);
    });

    it('re-translates when the English changes, unless the editor changed the Hindi in the same save', function () {
        fakeGoogleTranslate();
        $page = Page::factory()->create(['title' => 'Refund policy']);

        $page->update(['title' => 'Refunds and cancellations']);
        expect($page->fresh()->title_hi)->toBe('हिं Refunds and cancellations');

        $page->update(['title' => 'Cancellations', 'title_hi' => 'रद्द करने की नीति']);
        expect($page->fresh()->title_hi)->toBe('रद्द करने की नीति');

        $page->update(['excerpt' => 'Changed only the excerpt']);
        expect($page->fresh()->title_hi)->toBe('रद्द करने की नीति');
    });

    it('never overwrites Hindi an editor wrote after the translation was queued', function () {
        Queue::fake();
        $page = Page::factory()->create(['title' => 'Refund policy']);
        $job = Queue::pushed(TranslateContentToHindi::class)->first();

        $page->updateQuietly(['title_hi' => 'संपादक की लिखी हिंदी']);
        fakeGoogleTranslate();
        $job->handle(app(MachineTranslator::class));

        expect($page->fresh()->title_hi)->toBe('संपादक की लिखी हिंदी');
        Http::assertNotSent(fn (Request $request) => str_contains(implode(' ', $request['q']), 'Refund policy'));
    });

    it('does not change the record\'s last-updated time', function () {
        fakeGoogleTranslate();
        $this->travelTo('2026-10-15 10:00:00');
        Queue::fake();
        $page = Page::factory()->create(['title' => 'Refund policy']);
        $job = Queue::pushed(TranslateContentToHindi::class)->first();

        $this->travelTo('2026-10-16 10:00:00');
        $job->handle(app(MachineTranslator::class));

        expect($page->fresh()->updated_at->toDateString())->toBe('2026-10-15');
    });
});

describe('saving settings', function () {
    it('translates reworded English settings into their Hindi sibling', function () {
        fakeGoogleTranslate();

        Setting::set('site_tagline', 'Loans made simple');
        finishRequest();

        expect(Setting::get('site_tagline_hi'))->toBe('हिं Loans made simple');
    });

    it('keeps Hindi the editor saved alongside the English', function () {
        Queue::fake();

        Setting::set('site_tagline', 'Loans made simple');
        Setting::set('site_tagline_hi', 'लोन आसान');
        finishRequest();

        Queue::assertNotPushed(TranslateSettingToHindi::class);
        expect(Setting::get('site_tagline_hi'))->toBe('लोन आसान');
    });

    it('translates only the reworded pieces of page copy', function () {
        fakeGoogleTranslate();
        Setting::set('about_page', ['mission_label' => 'Our mission', 'mission_title' => 'Old title']);
        Setting::set('about_page_hi', ['mission_title' => 'पुराना शीर्षक']);
        finishRequest();
        Http::fake()->recorded();

        Setting::set('about_page', ['mission_label' => 'Our mission', 'mission_title' => 'A brand new title']);
        finishRequest();

        expect(Setting::get('about_page_hi'))->toBe(['mission_title' => 'हिं A brand new title']);
    });

    it('ignores settings visitors never read', function () {
        Queue::fake();

        Setting::set('theme_header_bg_color', '#ffffff');
        finishRequest();

        Queue::assertNothingPushed();
    });
});

it('fills every piece of content still missing Hindi', function () {
    // Saved while no key was configured, so nothing translated them at the time.
    config(['services.google_translate.key' => null]);
    $page = Page::factory()->create(['title' => 'Refund policy']);
    Setting::set('footer_disclaimer', 'Loans are subject to approval.');
    config(['services.google_translate.key' => 'test-key']);
    fakeGoogleTranslate();

    $this->artisan('translations:fill-hindi', ['--sync' => true])->assertSuccessful();

    expect($page->fresh()->title_hi)->toBe('हिं Refund policy')
        ->and(Setting::get('footer_disclaimer_hi'))->toBe('हिं Loans are subject to approval.');
});

it('reports that it can do nothing without a Google key', function () {
    config(['services.google_translate.key' => null]);

    $this->artisan('translations:fill-hindi')
        ->expectsOutputToContain('GOOGLE_TRANSLATE_API_KEY is not set')
        ->assertSuccessful();
});
