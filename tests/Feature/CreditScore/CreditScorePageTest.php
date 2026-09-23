<?php

use App\Enums\PublishStatus;
use App\Models\CreditScorePage;
use App\Models\Faq;
use App\Models\Setting;
use App\Modules\CreditScore\Enums\BureauName;
use App\Support\Pages\CreditScoreIndexing;
use App\Support\Pages\CreditScorePageContent;

it('gives every bureau page its own heading, content, meta tags and robots value', function () {
    Setting::set(CreditScorePageContent::settingKey(BureauName::Cibil), [
        'heading' => 'TEST CIBIL PAGE',
        'meta_title' => 'What is CIBIL Score?',
        'meta_description' => 'Learn about CIBIL score.',
    ]);
    Setting::set(CreditScorePageContent::settingKey(BureauName::Experian), [
        'heading' => 'TEST EXPERIAN PAGE',
        'meta_title' => 'How to Check Experian Score',
        'meta_description' => 'Learn how to check your Experian score.',
    ]);
    CreditScorePage::factory()->create(['bureau' => BureauName::Cibil, 'title' => 'About CIBIL', 'body' => '<p>TEST CONTENT CIBIL</p>']);
    CreditScorePage::factory()->create(['bureau' => BureauName::Experian, 'title' => 'About Experian', 'body' => '<p>TEST CONTENT EXPERIAN</p>']);
    CreditScoreIndexing::setIndexable(BureauName::Cibil, true);

    $cibil = $this->get('/credit-score/cibil');
    $experian = $this->get('/credit-score/experian');

    $cibil->assertSee(['<title>What is CIBIL Score?', 'Learn about CIBIL score.', '<meta name="robots" content="index, follow">'], false)
        ->assertSee(['TEST CIBIL PAGE', 'About CIBIL', 'TEST CONTENT CIBIL'])
        ->assertDontSee(['TEST EXPERIAN PAGE', 'TEST CONTENT EXPERIAN', 'How to Check Experian Score']);
    $experian->assertSee(['<title>How to Check Experian Score', 'Learn how to check your Experian score.', '<meta name="robots" content="noindex, nofollow">'], false)
        ->assertSee(['TEST EXPERIAN PAGE', 'About Experian', 'TEST CONTENT EXPERIAN'])
        ->assertDontSee(['TEST CIBIL PAGE', 'TEST CONTENT CIBIL', 'What is CIBIL Score?']);
});

it('uses the default heading when a page row has none', function () {
    CreditScorePage::factory()->create(['bureau' => BureauName::Equifax, 'title' => null, 'body' => '<p>Equifax body.</p>']);

    $this->get('/credit-score/equifax')->assertSee(['About the Equifax score', 'Equifax body.']);
});

it('hides the About section when the body is only empty editor markup', function () {
    CreditScorePage::factory()->create(['bureau' => BureauName::Crif, 'title' => 'About CRIF', 'body' => '<p>&nbsp;</p>']);

    $this->get('/credit-score/crif')->assertOk()->assertDontSee('About CRIF');
});

it('opens an opted-in page to search engines in robots.txt and the sitemap, and no other page', function () {
    CreditScoreIndexing::setIndexable(BureauName::Experian, true);

    $robots = $this->get('/robots.txt')->getContent();
    $sitemap = $this->get('/sitemap.xml')->getContent();

    expect($robots)->toContain("Disallow: /credit-score/\n")
        ->toContain('Allow: /credit-score/experian')
        ->not->toContain('Allow: /credit-score/cibil');
    expect($sitemap)->toContain(route('credit-score.show', ['bureau' => 'experian']))
        ->not->toContain(route('credit-score.show', ['bureau' => 'cibil']));
});

it('shows an FAQ pinned to one bureau page only there, and one pinned to every page on all of them', function () {
    Faq::factory()->create(['question' => 'Only on CIBIL?', 'status' => PublishStatus::Published, 'placements' => ['credit-score.show:cibil']]);
    Faq::factory()->create(['question' => 'On every score page?', 'status' => PublishStatus::Published, 'placements' => ['credit-score.show']]);

    $this->get('/credit-score/cibil')->assertSee(['Only on CIBIL?', 'On every score page?']);
    $this->get('/credit-score/crif')->assertSee('On every score page?')->assertDontSee('Only on CIBIL?');
});
