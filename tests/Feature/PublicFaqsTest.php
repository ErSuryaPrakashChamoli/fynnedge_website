<?php

use App\Enums\PublishStatus;
use App\Models\Faq;
use App\Models\LoanProduct;

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
