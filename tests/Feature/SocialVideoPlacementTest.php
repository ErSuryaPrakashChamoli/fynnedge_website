<?php

use App\Enums\FaqPlacement;
use App\Enums\SocialPlatform;
use App\Models\MarketingSection;
use App\Models\Setting;
use App\Models\SocialVideo;
use App\Models\VideoTestimonial;
use App\Support\SocialVideos\SocialVideos;

it('shows social media videos on the homepage just above the customer video testimonials', function () {
    SocialVideo::factory()->onPages([FaqPlacement::Home->value])->create(['title' => 'Three ways to cut your EMI']);
    VideoTestimonial::factory()->onPages([FaqPlacement::Home->value])->create(['customer_name' => 'Ravi Menon']);

    $this->get('/')
        ->assertOk()
        ->assertSeeInOrder([
            'data-ai-context="Social media videos"',
            'Three ways to cut your EMI',
            'data-ai-context="Customer video testimonials"',
            'Ravi Menon',
        ], false);
});

it('renders the social media videos section once on the homepage', function () {
    SocialVideo::factory()->onEveryPage()->create();

    $content = $this->get('/')->getContent();

    expect(substr_count($content, 'data-ai-context="Social media videos"'))->toBe(1);
});

it('shows every published social media video in the row, not just the five that fit on screen', function () {
    SocialVideo::factory()->count(8)->onPages([FaqPlacement::Home->value])->create();

    $content = $this->get('/')->getContent();

    expect(preg_match_all('/\sdata-social-video-card\s/', $content))->toBe(8);
});

it('shows a social media video only on the pages it is pinned to', function () {
    SocialVideo::factory()->onPages([FaqPlacement::Contact->value])->create(['title' => 'Meet the FynnEdge team']);

    $this->get('/contact')->assertSee('Meet the FynnEdge team');
    $this->get('/resources')->assertDontSee('Meet the FynnEdge team');
});

it('shows a site-wide social media video on every page', function () {
    SocialVideo::factory()->onEveryPage()->create(['title' => 'Home loan myths, busted']);

    $this->get('/contact')->assertSee('Home loan myths, busted');
    $this->get('/resources')->assertSee('Home loan myths, busted');
});

it('hides draft and expired social media videos, and the section with them', function () {
    SocialVideo::factory()->onEveryPage()->draft()->create(['title' => 'Draft reel']);
    SocialVideo::factory()->onEveryPage()->create(['title' => 'Expired reel', 'expires_at' => now()->subDay()]);

    $this->get('/contact')
        ->assertDontSee('Draft reel')
        ->assertDontSee('Expired reel')
        ->assertDontSee('data-ai-context="Social media videos"', false);
});

it('skips a YouTube social media video whose link is not a playable YouTube video', function () {
    SocialVideo::factory()->onEveryPage()->youtube('https://example.com/shorts/dQw4w9WgXcQ')->create(['title' => 'Broken link reel']);

    $this->get('/contact')->assertDontSee('Broken link reel');
});

it('renders the shared video player on a page with social media videos but no video testimonials', function () {
    SocialVideo::factory()->onPages([FaqPlacement::Contact->value])->create();

    $this->get('/contact')->assertSee('@open-video-testimonial.window', false);
});

it('uses the built-in wording until a social videos marketing section is published', function () {
    SocialVideo::factory()->onEveryPage()->create();

    $this->get('/contact')
        ->assertSee('As seen on social')
        ->assertSee('Loan clarity, one reel at a time.');
});

it('uses the badge, heading, intro and button published in the social videos marketing section', function () {
    SocialVideo::factory()->onEveryPage()->create();
    MarketingSection::factory()->published()->create([
        'placement' => SocialVideos::SECTION_PLACEMENT,
        'subheading' => 'Fresh from our feed',
        'heading' => 'Money tips in a minute',
        'description' => 'Short videos from the FynnEdge socials.',
        'cta_label' => 'Follow us on Instagram',
        'cta_url' => 'https://www.instagram.com/fynnedge',
    ]);

    $this->get('/contact')
        ->assertSee('Fresh from our feed')
        ->assertSee('Money tips in a minute')
        ->assertSee('Short videos from the FynnEdge socials.')
        ->assertSee('Follow us on Instagram')
        ->assertSee('href="https://www.instagram.com/fynnedge"', false)
        ->assertDontSee('Loan clarity, one reel at a time.');
});

it('keeps the built-in wording for a field left blank in the marketing section', function () {
    SocialVideo::factory()->onEveryPage()->create();
    MarketingSection::factory()->published()->create([
        'placement' => SocialVideos::SECTION_PLACEMENT,
        'subheading' => null,
        'heading' => 'Money tips in a minute',
        'description' => null,
    ]);

    $this->get('/contact')
        ->assertSee('As seen on social')
        ->assertSee('Money tips in a minute');
});

it('does not render a section button whose link is not a web address or site path', function () {
    SocialVideo::factory()->onEveryPage()->create();
    MarketingSection::factory()->published()->create([
        'placement' => SocialVideos::SECTION_PLACEMENT,
        'heading' => 'Money tips in a minute',
        'cta_label' => 'Follow us',
        'cta_url' => 'javascript:alert(1)',
    ]);

    $this->get('/contact')->assertDontSee('javascript:alert(1)', false);
});

it('links to each FynnEdge profile set under Settings, and only those', function () {
    Setting::set('social_instagram', 'https://www.instagram.com/fynnedge');
    Setting::set('social_linkedin', 'https://www.linkedin.com/company/fynnedge');
    SocialVideo::factory()->onEveryPage()->create();

    $this->get('/contact')
        ->assertSee('aria-label="Follow FynnEdge on Instagram"', false)
        ->assertSee('aria-label="Follow FynnEdge on LinkedIn"', false)
        ->assertDontSee('Follow FynnEdge on Facebook');
});

it('shows the short info beside the platform icon instead of the platform name', function () {
    SocialVideo::factory()->onEveryPage()->create(['platform' => SocialPlatform::Instagram, 'tagline' => 'Home loan tips · 45 sec']);

    $this->get('/contact')
        ->assertSee('>Home loan tips · 45 sec</p>', false)
        ->assertDontSee('>Instagram</p>', false);
});

it('shows the platform name beside the icon when the short info is blank', function () {
    SocialVideo::factory()->onEveryPage()->create(['platform' => SocialPlatform::LinkedIn, 'tagline' => null]);

    $this->get('/contact')->assertSee('>LinkedIn</p>', false);
});

it('links each card to its original post on that platform', function () {
    SocialVideo::factory()->onEveryPage()->create([
        'platform' => SocialPlatform::LinkedIn,
        'post_url' => 'https://www.linkedin.com/posts/fynnedge_rates-update',
    ]);

    $this->get('/contact')
        ->assertSee('href="https://www.linkedin.com/posts/fynnedge_rates-update"', false)
        ->assertSee('View on LinkedIn');
});

it('never renders a post link that is not an http(s) address', function () {
    SocialVideo::factory()->onEveryPage()->create(['post_url' => 'javascript:alert(1)']);

    $this->get('/contact')
        ->assertDontSee('javascript:alert(1)', false)
        ->assertDontSee('View on Instagram');
});

it('escapes admin-entered text in the social media videos section', function () {
    SocialVideo::factory()->onEveryPage()->create([
        'title' => '<script>alert(1)</script> Reel',
        'caption' => '<img src=x onerror=alert(2)>',
        'tagline' => '<i onclick=alert(5)>Info</i>',
    ]);
    MarketingSection::factory()->published()->create([
        'placement' => SocialVideos::SECTION_PLACEMENT,
        'heading' => '<script>alert(3)</script> Heading',
        'subheading' => '<b onmouseover=alert(4)>Badge</b>',
    ]);

    $response = $this->get('/contact');

    $response
        ->assertSee('<script>alert(1)</script> Reel')
        ->assertSee('<script>alert(3)</script> Heading');
    expect($response->getContent())
        ->not->toContain('<script>alert(1)</script>')
        ->not->toContain('<img src=x onerror=alert(2)>')
        ->not->toContain('<script>alert(3)</script>')
        ->not->toContain('<b onmouseover=alert(4)>')
        ->not->toContain('<i onclick=alert(5)>');
});
