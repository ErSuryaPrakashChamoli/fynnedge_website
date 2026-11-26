<?php

use App\Filament\Resources\DebtApplications\Pages\ViewDebtApplication;
use App\Models\User;
use App\Modules\Customers\Models\Customer;
use App\Modules\DebtConsolidation\Actions\AnonymiseDebtApplication;
use App\Modules\DebtConsolidation\Enums\ActorType;
use App\Modules\DebtConsolidation\Enums\DebtApplicationStatus;
use App\Modules\DebtConsolidation\Enums\LenderApplicationStatus;
use App\Modules\DebtConsolidation\Models\DebtApplication;
use App\Modules\DebtConsolidation\Models\DebtCalculationSnapshot;
use App\Modules\DebtConsolidation\Models\DebtClosure;
use App\Modules\DebtConsolidation\Models\DebtCollateral;
use App\Modules\DebtConsolidation\Models\DebtConsent;
use App\Modules\DebtConsolidation\Models\DebtDocument;
use App\Modules\DebtConsolidation\Models\DebtFinancialProfile;
use App\Modules\DebtConsolidation\Models\DebtForeclosureQuotation;
use App\Modules\DebtConsolidation\Models\DebtLenderApplication;
use App\Modules\DebtConsolidation\Models\DebtLenderMatch;
use App\Modules\DebtConsolidation\Models\DebtLiability;
use App\Modules\DebtConsolidation\Models\DebtNote;
use App\Modules\DebtConsolidation\Models\DebtReport;
use App\Modules\DebtConsolidation\Models\DebtScenario;
use App\Modules\DebtConsolidation\Services\DebtConsolidationRoles;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(fn () => Storage::fake('local'));

/**
 * A case with every kind of record that holds personal data.
 */
function caseWithPersonalData(): DebtApplication
{
    $application = DebtApplication::factory()->forCustomer(Customer::factory()->portalAccount('9811100001')->create())->create([
        'full_name' => 'Asha Verma', 'email' => 'asha@example.com', 'city' => 'Indore', 'pincode' => '452001', 'declared_total_outstanding' => 650000,
    ]);
    DebtFinancialProfile::factory()->for($application, 'application')->create();
    $liability = DebtLiability::factory()->for($application, 'application')->create();
    DebtForeclosureQuotation::factory()->for($liability, 'liability')->create();
    DebtCollateral::factory()->for($application, 'application')->create();
    DebtDocument::factory()->for($application, 'application')->withStoredFile('%PDF-1.4 salary slip')->create();
    $report = DebtReport::factory()->for($application, 'application')->create();
    Storage::disk('local')->put($report->path, Crypt::encryptString('%PDF-1.4 report'));
    DebtCalculationSnapshot::factory()->create(['debt_application_id' => $application->id]);
    DebtScenario::factory()->for($application, 'application')->create();
    DebtLenderMatch::factory()->for($application, 'application')->create();
    DebtNote::factory()->for($application, 'application')->create(['body' => 'Spoke to Asha about her card']);
    DebtConsent::factory()->for($application, 'application')->create();
    $application->recordStatusChange('draft', 'enquiry_received', ActorType::Staff, null, 'Asha called from her office');

    return $application;
}

it('removes a case\'s personal data and keeps its reference, dates, consents and status history', function () {
    $application = caseWithPersonalData();
    $mobile = $application->mobile;
    $files = collect([...DebtDocument::query()->pluck('path'), ...DebtReport::query()->pluck('path')]);

    app(AnonymiseDebtApplication::class)->handle($application, AnonymiseDebtApplication::REASON_REQUEST, null, 'REQ-2026-11');

    $case = DebtApplication::withTrashed()->find($application->id);
    expect($case)
        ->reference->toBe($application->reference)
        ->full_name->toBeNull()
        ->email->toBeNull()
        ->city->toBeNull()
        ->pincode->toBeNull()
        ->declared_total_outstanding->toBeNull()
        ->customer_id->toBeNull()
        ->status->toBe(DebtApplicationStatus::Withdrawn)
        ->and($case->isAnonymised())->toBeTrue()
        ->and($case->mobile)->not->toBe($mobile);

    foreach ([DebtFinancialProfile::class, DebtLiability::class, DebtForeclosureQuotation::class, DebtCollateral::class, DebtReport::class, DebtCalculationSnapshot::class, DebtScenario::class, DebtLenderMatch::class, DebtNote::class] as $model) {
        expect($model::query()->count())->toBe(0, $model);
    }

    expect(DebtDocument::withTrashed()->count())->toBe(0)
        ->and($files->filter(fn (string $path): bool => Storage::disk('local')->exists($path)))->toBeEmpty()
        ->and($case->consents()->count())->toBe(1)
        ->and($case->statusChanges()->pluck('note')->filter()->all())->toBe([])
        ->and($case->statusChanges()->count())->toBe(2)
        ->and($case->auditLogs()->where('action', 'personal_data_anonymised')->sole()->changes['reference']['new'])->toBe('REQ-2026-11');
});

it('refuses a case a lender has received or that has closure tracking', function (string $involvement) {
    $application = caseWithPersonalData();

    match ($involvement) {
        'lender application' => DebtLenderApplication::factory()->for($application, 'application')->create(['status' => LenderApplicationStatus::Submitted]),
        'closure' => DebtClosure::factory()->for($application->liabilities()->first(), 'liability')->create(['debt_application_id' => $application->id]),
    };

    expect(fn () => app(AnonymiseDebtApplication::class)->handle($application->fresh(), AnonymiseDebtApplication::REASON_REQUEST))
        ->toThrow(ValidationException::class, 'gone to a lender')
        ->and($application->fresh()->full_name)->toBe('Asha Verma')
        ->and(DebtLiability::query()->count())->toBe(1);
})->with(['lender application', 'closure']);

it('lets only staff allowed to delete cases anonymise one from the case page', function () {
    $this->seed(RoleSeeder::class);
    $application = caseWithPersonalData();

    $this->actingAs(tap(User::factory()->create(['is_admin' => true]))->syncRoles([DebtConsolidationRoles::ADVISOR_ROLE]));
    Livewire::test(ViewDebtApplication::class, ['record' => $application->getRouteKey()])->assertActionHidden('anonymise');

    $this->actingAs(User::factory()->create(['is_admin' => true]));
    Livewire::test(ViewDebtApplication::class, ['record' => $application->getRouteKey()])
        ->callAction('anonymise', ['reference' => 'REQ-7']);

    expect($application->fresh()->isAnonymised())->toBeTrue();
});
