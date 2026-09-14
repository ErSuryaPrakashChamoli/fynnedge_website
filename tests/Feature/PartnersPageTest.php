<?php

use App\Enums\LenderStatus;
use App\Enums\LenderType;
use App\Models\Lender;

it('lists every active lender as a card with its logo, grouped by type', function () {
    Lender::factory()->create(['name' => 'Kotak Mahindra Bank', 'type' => LenderType::Bank, 'logo_path' => 'lenders/kotak.png']);
    Lender::factory()->create(['name' => 'Tata Capital', 'type' => LenderType::Nbfc]);
    Lender::factory()->create(['name' => 'Retired Bank', 'type' => LenderType::Bank, 'status' => LenderStatus::Inactive]);

    $this->get(route('partners.index'))
        ->assertOk()
        ->assertSee('Our partner banks &amp; NBFCs', false)
        ->assertSeeInOrder(['Banks', 'Kotak Mahindra Bank', 'NBFCs & HFCs', 'Tata Capital'])
        ->assertSee('src="/storage/lenders/kotak.png"', false)
        ->assertDontSee('Retired Bank');
});

it('shows a notice instead of an empty grid when no lender is active', function () {
    $this->get(route('partners.index'))
        ->assertOk()
        ->assertSee('Our partner list is being updated.');
});
