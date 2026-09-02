<?php

use App\Enums\LoanCategory;
use App\Enums\PublishStatus;
use App\Filament\Resources\Testimonials\Pages\CreateTestimonial;
use App\Filament\Resources\Testimonials\Pages\EditTestimonial;
use App\Filament\Resources\Testimonials\Pages\ListTestimonials;
use App\Models\Testimonial;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
});

it('renders the testimonials index and edit pages', function () {
    $this->actingAs($this->admin);

    $testimonial = Testimonial::factory()->create();

    $this->get('/admin/testimonials')->assertOk();
    $this->get("/admin/testimonials/{$testimonial->public_id}/edit")->assertOk();
});

it('lists testimonials in the admin table', function () {
    $this->actingAs($this->admin);

    $testimonial = Testimonial::factory()->create();

    Livewire::test(ListTestimonials::class)
        ->assertCanSeeTableRecords([$testimonial]);
});

it('creates a testimonial scoped to a loan category', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateTestimonial::class)
        ->fillForm([
            'customer_name' => 'Test Customer',
            'role_location' => 'Personal Loan customer, Mumbai',
            'loan_category' => LoanCategory::PersonalLoan->value,
            'rating' => '5',
            'quote' => 'FynnEdge made comparing lenders simple.',
            'status' => PublishStatus::Published->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Testimonial::query()
        ->where('customer_name', 'Test Customer')
        ->where('loan_category', LoanCategory::PersonalLoan)
        ->exists())->toBeTrue();
});

it('redirects back to the listing page after creating a testimonial', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateTestimonial::class)
        ->fillForm([
            'customer_name' => 'Redirect Customer',
            'role_location' => 'Personal Loan customer, Mumbai',
            'loan_category' => LoanCategory::PersonalLoan->value,
            'rating' => '5',
            'quote' => 'FynnEdge made comparing lenders simple.',
            'status' => PublishStatus::Published->value,
        ])
        ->call('create')
        ->assertRedirect(ListTestimonials::getUrl());
});

it('redirects back to the listing page after saving a testimonial', function () {
    $this->actingAs($this->admin);

    $testimonial = Testimonial::factory()->create();

    Livewire::test(EditTestimonial::class, ['record' => $testimonial->public_id])
        ->fillForm(['customer_name' => 'Updated Customer'])
        ->call('save')
        ->assertRedirect(ListTestimonials::getUrl());
});
