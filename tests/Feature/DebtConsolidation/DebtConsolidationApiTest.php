<?php

use App\Models\ApiClient;
use App\Modules\DebtConsolidation\Actions\AssessDebtApplication;
use App\Modules\DebtConsolidation\Actions\RecordDebtConsent;
use App\Modules\DebtConsolidation\Enums\ActorType;
use App\Modules\DebtConsolidation\Enums\ConsentPurpose;
use App\Modules\DebtConsolidation\Models\DebtApplication;
use App\Modules\DebtConsolidation\Models\DebtLiability;
use App\Modules\DebtConsolidation\Services\DebtConsolidationRoles;
use App\Modules\DebtConsolidation\Services\Finance\FormulaVersion;

beforeEach(fn () => DebtConsolidationRoles::grant());

/**
 * @return array<string, string>
 */
function debtApiHeaders(string $role = DebtConsolidationRoles::ADVISOR_ROLE): array
{
    $client = ApiClient::factory()->create();
    $client->user->syncRoles([$role]);

    return ['Authorization' => 'Bearer '.$client->issueKey()];
}

function apiCase(bool $consented = true): DebtApplication
{
    $application = DebtApplication::factory()->submitted()->create(['full_name' => 'Secret Person', 'email' => 'secret@example.com']);
    DebtLiability::factory()->for($application, 'application')->create(['account_reference' => 'ACC123456789']);
    app(AssessDebtApplication::class)->handle($application, ActorType::System);

    if ($consented) {
        app(RecordDebtConsent::class)->handle($application, ConsentPurpose::ExternalAnalysis);
    }

    return $application;
}

it('returns the structured case and its stored calculation, without contact details', function () {
    $application = apiCase();

    $response = $this->getJson('/api/v1/external/debt-consolidation/cases/'.$application->reference, debtApiHeaders())
        ->assertOk()
        ->assertJsonPath('data.reference', $application->reference)
        ->assertJsonPath('data.liabilities.0.outstanding_principal', 500000)
        ->assertJsonPath('data.assessment.formula_version', FormulaVersion::CURRENT);

    expect($response->getContent())->not->toContain('Secret Person')->not->toContain('secret@example.com')
        ->not->toContain($application->mobile)->not->toContain('ACC123456789')
        ->and($application->auditLogs()->where('action', 'case_read_by_api_client')->exists())->toBeTrue();
});

it('answers 403 consent_required without the customer\'s external-analysis consent', function () {
    $application = apiCase(consented: false);

    $this->getJson('/api/v1/external/debt-consolidation/cases/'.$application->reference, debtApiHeaders())
        ->assertForbidden()
        ->assertJsonPath('error.code', 'consent_required');
});

it('answers 403 to a key whose role may not view cases', function () {
    $application = apiCase();

    $this->getJson('/api/v1/external/debt-consolidation/cases/'.$application->reference, debtApiHeaders('Marketing'))->assertForbidden();
});

it('answers 401 without a valid key', function () {
    $this->getJson('/api/v1/external/debt-consolidation/cases/'.apiCase()->reference, ['Authorization' => 'Bearer fyn_nope'])->assertUnauthorized();
});

it('does not tell a key that may not view cases which references exist', function () {
    apiCase();

    $this->getJson('/api/v1/external/debt-consolidation/cases/DC-NOTREAL1', debtApiHeaders('Marketing'))->assertForbidden();
});

it('answers 404 for an unknown reference to a key that may view cases', function () {
    $this->getJson('/api/v1/external/debt-consolidation/cases/DC-NOTREAL1', debtApiHeaders())
        ->assertNotFound()
        ->assertJsonPath('error.code', 'not_found');
});
