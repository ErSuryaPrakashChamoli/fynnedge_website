<?php

namespace Database\Seeders;

use App\Enums\LoanCategory;
use App\Enums\PublishStatus;
use App\Models\Testimonial;
use Illuminate\Database\Seeder;

/**
 * General testimonials (loan_category null) show on every loan page.
 * Category-specific ones show alongside the general ones on that category's
 * loan type and sub-loan-type pages — see Testimonial::scopeForCategory().
 */
class TestimonialSeeder extends Seeder
{
    public function run(): void
    {
        $testimonials = [
            [
                'customer_name' => 'Ananya Rao',
                'role_location' => 'Bengaluru',
                'loan_category' => null,
                'rating' => 5,
                'quote' => 'I compared three lenders on FynnEdge in minutes instead of visiting three branches. The eligibility check was upfront and honest about what I actually qualified for.',
            ],
            [
                'customer_name' => 'Vikram Singh',
                'role_location' => 'Delhi',
                'loan_category' => null,
                'rating' => 5,
                'quote' => 'What I liked most was there were no surprise charges — everything the lender would charge was shown before I applied.',
            ],
            [
                'customer_name' => 'Priya Nair',
                'role_location' => 'Personal Loan customer, Kochi',
                'loan_category' => LoanCategory::PersonalLoan,
                'rating' => 5,
                'quote' => 'Needed funds quickly for a family medical expense. FynnEdge matched me to a lender I was actually eligible for, and the whole process was online.',
            ],
            [
                'customer_name' => 'Rohit Malhotra',
                'role_location' => 'Home Loan customer, Pune',
                'loan_category' => LoanCategory::HomeLoan,
                'rating' => 5,
                'quote' => 'The home loan EMI calculator helped me plan my budget before I even applied. Comparing lenders side by side made the decision easy.',
            ],
            [
                'customer_name' => 'Sneha Deshmukh',
                'role_location' => 'Car Loan customer, Nagpur',
                'loan_category' => LoanCategory::CarLoan,
                'rating' => 4,
                'quote' => 'Got a clear picture of interest rates and processing fees across lenders before walking into the showroom to finalise my car.',
            ],
            [
                'customer_name' => 'Arjun Mehta',
                'role_location' => 'Business Loan customer, Ahmedabad',
                'loan_category' => LoanCategory::BusinessLoan,
                'rating' => 5,
                'quote' => 'As a small business owner, I did not have time to chase multiple banks. FynnEdge showed me which lenders fit my profile straightaway.',
            ],
        ];

        foreach ($testimonials as $index => $testimonial) {
            Testimonial::query()->updateOrCreate(
                ['customer_name' => $testimonial['customer_name'], 'loan_category' => $testimonial['loan_category']],
                [
                    'role_location' => $testimonial['role_location'],
                    'rating' => $testimonial['rating'],
                    'quote' => $testimonial['quote'],
                    'sort_order' => $index + 1,
                    'status' => PublishStatus::Published,
                    'published_at' => now(),
                ],
            );
        }
    }
}
