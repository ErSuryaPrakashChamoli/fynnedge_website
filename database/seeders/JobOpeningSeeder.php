<?php

namespace Database\Seeders;

use App\Enums\PublishStatus;
use App\Models\JobOpening;
use Illuminate\Database\Seeder;

class JobOpeningSeeder extends Seeder
{
    public function run(): void
    {
        $titles = [
            'Sourcing Specialist - Personal Loan',
            'Team Leader - Personal Loan',
            'Digital Marketing Manager',
            'Business Development',
        ];

        foreach ($titles as $index => $title) {
            JobOpening::query()->updateOrCreate(
                ['title' => $title],
                [
                    'sort_order' => $index + 1,
                    'status' => PublishStatus::Published,
                ],
            );
        }
    }
}
