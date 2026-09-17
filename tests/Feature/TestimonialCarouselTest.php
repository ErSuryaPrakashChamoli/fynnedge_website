<?php

use App\Models\Testimonial;

/*
 * Written testimonials render as one horizontally scrolling row with
 * previous/next arrows, so more than three can be published without the
 * section growing into extra rows. Every published testimonial is in the
 * markup; the arrows scroll the row rather than paginating it.
 */

it('renders every published homepage testimonial in a scrollable row with arrows', function () {
    $testimonials = Testimonial::factory()->count(5)->create(['loan_category' => null]);

    $html = $this->get('/')->assertOk()->getContent();

    foreach ($testimonials as $testimonial) {
        expect($html)->toContain(e($testimonial->quote));
    }

    expect(substr_count($html, 'data-testimonial-card'))->toBe(5)
        ->and($html)->toContain('aria-label="Previous testimonials"')
        ->and($html)->toContain('aria-label="More testimonials"')
        ->and($html)->toContain('snap-x snap-mandatory gap-4 overflow-x-auto');
});
