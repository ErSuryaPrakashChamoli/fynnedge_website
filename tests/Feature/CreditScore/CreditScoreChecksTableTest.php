<?php

use App\Filament\Resources\CreditScoreChecks\Pages\ListCreditScoreChecks;
use App\Models\User;
use App\Modules\CreditScore\Enums\BureauName;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use Livewire\Livewire;

it('filters by application date in India time', function () {
    $lateOnTheTwentieth = CreditScoreCheck::factory()->create(['created_at' => '2026-10-20 18:00:00']);
    $earlyOnTheTwentyFirst = CreditScoreCheck::factory()->create(['created_at' => '2026-10-20 20:00:00']);
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $page = Livewire::test(ListCreditScoreChecks::class)
        ->filterTable('created_at', ['from' => '2026-10-21', 'until' => '2026-10-21']);

    $page->assertCanSeeTableRecords([$earlyOnTheTwentyFirst])
        ->assertCanNotSeeTableRecords([$lateOnTheTwentieth]);
});

it('filters by a score range, leaving out checks with no score', function () {
    $inRange = CreditScoreCheck::factory()->sequence(['score' => 650], ['score' => 749])->count(2)->create();
    $outOfRange = CreditScoreCheck::factory()->sequence(['score' => 649], ['score' => 750], ['score' => null])->count(3)->create();
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $page = Livewire::test(ListCreditScoreChecks::class)
        ->filterTable('score', ['min' => '650', 'max' => '749']);

    $page->assertCanSeeTableRecords($inRange)
        ->assertCanNotSeeTableRecords($outOfRange);
});

it('filters by several bureaus at once', function () {
    $cibil = CreditScoreCheck::factory()->create(['bureau' => BureauName::Cibil]);
    $experian = CreditScoreCheck::factory()->create(['bureau' => BureauName::Experian]);
    $crif = CreditScoreCheck::factory()->create(['bureau' => BureauName::Crif]);
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $page = Livewire::test(ListCreditScoreChecks::class)
        ->filterTable('bureau', [BureauName::Cibil, BureauName::Experian]);

    $page->assertCanSeeTableRecords([$cibil, $experian])
        ->assertCanNotSeeTableRecords([$crif]);
});

it('downloads only the selected checks', function () {
    $this->travelTo('2026-10-21 04:35:00');
    $selected = CreditScoreCheck::factory()->create([
        'bureau' => BureauName::Experian,
        'full_name' => 'Shivam Test',
        'mobile_number' => '7982177401',
        'pan_number' => 'DZXPG2242B',
        'score' => 698,
    ]);
    CreditScoreCheck::factory()->create();
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $page = Livewire::test(ListCreditScoreChecks::class)
        ->callTableBulkAction('downloadSelected', [$selected]);

    $page->assertFileDownloaded(
        'credit-score-checks-2026-10-21.csv',
        "Name,\"Mobile number\",Bureau,Score,Location,PAN,\"Date of application (IST)\"\n"
        ."\"Shivam Test\",7982177401,Experian,698,,DZXPG2242B,\"2026-10-21 10:05\"\n",
    );
});
