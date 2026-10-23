<?php

use App\Enums\PublishStatus;
use App\Models\Faq;
use App\Models\LoanProduct;
use App\Models\Page;

it('shows the FAQs nav link in the header', function () {
    $this->get('/')->assertOk()->assertSee(route('faqs.index'), false);
});

it('renders only published, general FAQs on the FAQs page', function () {
    $general = Faq::factory()->create(['question' => 'What does FynnEdge do?']);
    $draft = Faq::factory()->create(['question' => 'Unpublished question', 'status' => PublishStatus::Draft]);
    $productScoped = Faq::factory()->for(LoanProduct::factory()->create(), 'faqable')->create(['question' => 'Product-specific question']);

    $response = $this->get('/faqs');

    $response->assertOk();
    $response->assertSee('What does FynnEdge do?');
    $response->assertDontSee('Unpublished question');
    $response->assertDontSee('Product-specific question');
});

it('orders FAQs by sort_order', function () {
    Faq::factory()->create(['question' => 'Second question', 'sort_order' => 2]);
    Faq::factory()->create(['question' => 'First question', 'sort_order' => 1]);

    $response = $this->get('/faqs');

    $response->assertOk();
    $firstPos = strpos($response->getContent(), 'First question');
    $secondPos = strpos($response->getContent(), 'Second question');
    expect($firstPos)->toBeLessThan($secondPos);
});

it('shows a friendly message when there are no FAQs yet', function () {
    Faq::query()->delete();

    $this->get('/faqs')->assertOk()->assertSee('being added');
});

it('takes its heading, intro and SEO from the faqs page under Content → Pages', function () {
    $page = Page::query()->where('slug', 'faqs')->sole();
    $page->update(['title' => 'Loan questions, answered', 'excerpt' => 'Short answers first.', 'body' => '<p>Still stuck? Call us.</p>']);
    $page->seoMeta()->create(['title' => 'FAQs about loans', 'description' => 'Every loan question in one place.', 'robots' => 'noindex, follow']);

    $this->get('/faqs')
        ->assertOk()
        ->assertSee('Loan questions, answered')
        ->assertSee('Short answers first.')
        ->assertSee('<p>Still stuck? Call us.</p>', false)
        ->assertSee('<title>FAQs about loans', false)
        ->assertSee('content="Every loan question in one place."', false)
        ->assertSee('content="noindex, follow"', false);
});

it('shows FAQs added on the faqs page itself after the general ones', function () {
    Faq::factory()->create(['question' => 'General question?', 'sort_order' => 5]);
    Page::query()->where('slug', 'faqs')->sole()->faqs()->create([
        'question' => 'Question added on the page?',
        'answer' => 'Answer.',
        'sort_order' => 0,
        'status' => PublishStatus::Published,
    ]);

    $this->get('/faqs')
        ->assertOk()
        ->assertSeeInOrder(['General question?', 'Question added on the page?']);
});
