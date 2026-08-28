<?php

use App\Models\Faq;
use App\Models\Lender;
use App\Models\LoanProduct;
use App\Models\Page;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
});

it('renders every CMS resource index page', function () {
    $this->actingAs($this->admin);

    $this->get('/admin/pages')->assertOk();
    $this->get('/admin/loan-products')->assertOk();
    $this->get('/admin/lenders')->assertOk();
    $this->get('/admin/faqs')->assertOk();
    $this->get('/admin/settings')->assertOk();
});

it('renders edit pages for existing records', function () {
    $this->actingAs($this->admin);

    $page = Page::factory()->create();
    $loanProduct = LoanProduct::factory()->create();
    $lender = Lender::factory()->create();
    $faq = Faq::factory()->create();

    $this->get("/admin/pages/{$page->public_id}/edit")->assertOk();
    $this->get("/admin/loan-products/{$loanProduct->public_id}/edit")->assertOk();
    $this->get("/admin/lenders/{$lender->public_id}/edit")->assertOk();
    $this->get("/admin/faqs/{$faq->public_id}/edit")->assertOk();
});

it('scopes the general FAQ resource to FAQs without a parent', function () {
    $this->actingAs($this->admin);

    $general = Faq::factory()->create();
    $loanProduct = LoanProduct::factory()->create();
    $attached = Faq::factory()->for($loanProduct, 'faqable')->create();

    $response = $this->get('/admin/faqs');

    $response->assertOk();
    $response->assertSee($general->question);
    $response->assertDontSee($attached->question);
});
