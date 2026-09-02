<?php

use App\Filament\Resources\Employers\Pages\ListEmployers;
use App\Models\User;
use App\Modules\Eligibility\Models\Employer;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
});

it('lists employers and offers a bulk-upload action', function () {
    Employer::factory()->count(3)->create();

    Livewire::test(ListEmployers::class)
        ->assertSuccessful()
        ->assertActionExists('import');
});
