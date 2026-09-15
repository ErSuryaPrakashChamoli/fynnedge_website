<?php

use App\Enums\FaqPlacement;
use App\Enums\LoanCategory;
use App\Models\LoanProduct;
use App\Models\VideoTestimonial;

it('shows a video testimonial only on the pages it is pinned to', function () {
    VideoTestimonial::factory()->onPages([FaqPlacement::Contact->value])->create(['customer_name' => 'Ravi Menon']);

    $this->get('/contact')->assertSee('Ravi Menon');
    $this->get('/resources')->assertDontSee('Ravi Menon');
});

it('shows a site-wide video testimonial on every page', function () {
    VideoTestimonial::factory()->onEveryPage()->create(['customer_name' => 'Priya Nair']);

    $this->get('/contact')->assertSee('Priya Nair');
    $this->get('/resources')->assertSee('Priya Nair');
});

it('shows a video pinned to one loan product once, on that product only', function () {
    $pinnedProduct = LoanProduct::factory()->published()->create(['slug' => 'video-pinned-loan', 'category' => LoanCategory::PersonalLoan]);
    $otherProduct = LoanProduct::factory()->published()->create(['slug' => 'video-other-loan', 'category' => LoanCategory::PersonalLoan]);
    VideoTestimonial::factory()->onPages(['loans.show:video-pinned-loan'])->create(['customer_name' => 'Kiran Rao']);

    $pinnedResponse = $this->get("/loans/{$pinnedProduct->slug}");
    $otherResponse = $this->get("/loans/{$otherProduct->slug}");

    $pinnedResponse->assertSee('Kiran Rao');
    expect(substr_count($pinnedResponse->getContent(), 'data-ai-context="Customer video testimonials"'))->toBe(1);
    $otherResponse->assertDontSee('Kiran Rao');
});

it('does not show an unpublished video testimonial', function () {
    VideoTestimonial::factory()->onEveryPage()->draft()->create(['customer_name' => 'Draft Customer']);
    VideoTestimonial::factory()->onEveryPage()->create(['customer_name' => 'Expired Customer', 'expires_at' => now()->subDay()]);

    $this->get('/contact')
        ->assertDontSee('Draft Customer')
        ->assertDontSee('Expired Customer')
        ->assertDontSee('data-ai-context="Customer video testimonials"', false);
});

it('skips a YouTube testimonial whose link is not a playable YouTube video', function () {
    VideoTestimonial::factory()->onEveryPage()->youtube('https://example.com/watch?v=dQw4w9WgXcQ')->create(['customer_name' => 'Broken Link']);

    $this->get('/contact')->assertDontSee('Broken Link');
});

it('uses the YouTube thumbnail as the cover of a YouTube testimonial without its own', function () {
    VideoTestimonial::factory()->onEveryPage()->youtube('https://youtu.be/dQw4w9WgXcQ')->create();

    $this->get('/contact')->assertSee('https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg', false);
});

it('pops up the video an admin marked to float in the corner', function () {
    VideoTestimonial::factory()->onEveryPage()->floating()->create(['customer_name' => 'Meera Iyer']);

    $this->get('/contact')
        ->assertSee('data-video-testimonial-bubble', false)
        ->assertSee("Watch Meera's story", false);
});

it('does not pop up a video that is not marked to float', function () {
    VideoTestimonial::factory()->onEveryPage()->create(['customer_name' => 'Section Only']);

    $this->get('/contact')
        ->assertSee('Section Only')
        ->assertDontSee('data-video-testimonial-bubble', false);
});

it('shows each customer in their own card with their photo, or their initial when there is none', function () {
    VideoTestimonial::factory()->onEveryPage()->create([
        'customer_name' => 'Anjali Kapoor',
        'customer_photo_path' => 'video-testimonial-photos/anjali.jpg',
        'customer_photo_alt' => 'Anjali smiling',
        'quote' => 'The whole process took less than a week.',
    ]);
    VideoTestimonial::factory()->onEveryPage()->create(['customer_name' => 'Zubin Shah', 'customer_photo_path' => null]);

    $response = $this->get('/contact');

    $response
        ->assertSee('src="/storage/video-testimonial-photos/anjali.jpg"', false)
        ->assertSee('alt="Anjali smiling"', false)
        ->assertSee('The whole process took less than a week.')
        ->assertSee('Watch Anjali&rsquo;s story', false);
    expect(preg_match_all('/\sdata-video-card\s/', $response->getContent()))->toBe(2);
});

it('escapes admin-entered text on the video testimonial card and pop-up', function () {
    VideoTestimonial::factory()->onEveryPage()->floating()->create([
        'customer_name' => '<script>alert(1)</script> Asha',
        'headline' => '<img src=x onerror=alert(2)>',
    ]);

    $response = $this->get('/contact');

    $response->assertSee('<script>alert(1)</script> Asha');
    expect($response->getContent())
        ->not->toContain('<script>alert(1)</script>')
        ->not->toContain('<img src=x onerror=alert(2)>');
});
