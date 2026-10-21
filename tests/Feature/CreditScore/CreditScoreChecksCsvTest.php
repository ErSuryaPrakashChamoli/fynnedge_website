<?php

use App\Filament\Resources\CreditScoreChecks\Pages\ListCreditScoreChecks;
use App\Models\User;
use App\Modules\CreditBureau\Enums\CreditCheckStatus;
use App\Modules\CreditScore\Enums\BureauName;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use App\Modules\CreditScore\Services\CreditScoreChecksCsv;
use App\Modules\Customers\Models\Customer;
use App\Modules\Experian\Models\ExperianCreditRequest;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

/**
 * A live CIBIL-page check answered by Experian, whose full report (the
 * Surepass fixture) lists "1 Fixture Lane, Test Nagar, TESTPUR", PIN 400001.
 */
function cibilCheckWithExperianReport(): CreditScoreCheck
{
    $request = ExperianCreditRequest::factory()->successful(experianFixture('report'))->create();

    return CreditScoreCheck::factory()->create([
        'bureau' => BureauName::Cibil,
        'provider' => CreditScoreCheck::LIVE_PROVIDER,
        'status' => CreditCheckStatus::Completed,
        'raw_response' => ['source_bureau' => BureauName::Experian->value, 'experian_request' => $request->public_id],
    ]);
}

it('downloads only the checks the table filters leave in view', function () {
    $this->travelTo('2026-10-21 04:35:00');
    CreditScoreCheck::factory()->create([
        'bureau' => BureauName::Cibil,
        'full_name' => 'Bharti Test',
        'mobile_number' => '8506019024',
        'pan_number' => 'GOGPB1515B',
        'score' => 763,
    ]);
    CreditScoreCheck::factory()->create(['bureau' => BureauName::Crif]);
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $page = Livewire::test(ListCreditScoreChecks::class)
        ->filterTable('bureau', BureauName::Cibil->value)
        ->callAction('downloadCsv');

    $page->assertFileDownloaded(
        'credit-score-checks-2026-10-21.csv',
        "Name,\"Mobile number\",Bureau,Score,Location,PAN,\"Date of application (IST)\"\n"
        ."\"Bharti Test\",8506019024,CIBIL,763,,GOGPB1515B,\"2026-10-21 10:05\"\n",
    );
});

it('takes the location from the customer profile before the bureau report', function () {
    $check = cibilCheckWithExperianReport();
    $check->customer()->associate(Customer::factory()->create(['city' => 'Pune', 'state' => 'Maharashtra', 'pincode' => '411001']))->save();

    $location = CreditScoreChecksCsv::location($check, User::factory()->create(['is_admin' => true]));

    expect($location)->toBe('Pune, Maharashtra 411001');
});

it('takes the location from the report\'s first address and names the bureau that answered', function () {
    $check = cibilCheckWithExperianReport();

    $row = CreditScoreChecksCsv::row($check, User::factory()->create(['is_admin' => true]));

    expect($row[2])->toBe('CIBIL (answered by Experian)')
        ->and($row[4])->toBe('1 Fixture Lane, Test Nagar, TESTPUR, 400001');
});

it('leaves the location blank for staff who may not read the full report', function () {
    $check = cibilCheckWithExperianReport();
    $staff = tap(User::factory()->create(['is_admin' => true]))->syncRoles([]);
    $staff->givePermissionTo(collect(['ViewAny:CreditScoreCheck', 'View:CreditScoreCheck', 'ViewAny:ExperianCreditRequest', 'View:ExperianCreditRequest'])
        ->map(fn (string $permission): Permission => Permission::findOrCreate($permission)));

    $location = CreditScoreChecksCsv::location($check, $staff);

    expect($location)->toBeNull();
});

it('neutralises spreadsheet formulas in the downloaded cells', function () {
    CreditScoreCheck::factory()->create(['full_name' => '=HYPERLINK("https://evil.example")']);

    ob_start();
    CreditScoreChecksCsv::download(CreditScoreCheck::query(), null)->sendContent();
    $csv = ob_get_clean();

    expect($csv)
        ->toContain("'=HYPERLINK")
        ->not->toContain("\n=HYPERLINK");
});
