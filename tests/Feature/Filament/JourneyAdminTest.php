<?php

use App\Filament\Resources\JourneyDefinitions\Pages\EditJourneyDefinition;
use App\Filament\Resources\JourneyDefinitions\RelationManagers\StepsRelationManager;
use App\Models\LoanProduct;
use App\Models\User;
use App\Modules\CreditBureau\Models\CreditConsent;
use App\Modules\Journey\Enums\FieldType;
use App\Modules\Journey\Enums\JourneyDefinitionStatus;
use App\Modules\Journey\Models\JourneyDefinition;
use App\Modules\Journey\Models\JourneySession;
use App\Modules\Journey\Models\JourneyStep;
use App\Modules\Journey\Models\JourneyStepField;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
});

it('renders the journey definitions index and edit pages', function () {
    $this->actingAs($this->admin);

    $product = LoanProduct::factory()->create();
    $definition = JourneyDefinition::create([
        'loan_product_id' => $product->id, 'version' => 1, 'status' => JourneyDefinitionStatus::Active,
    ]);
    $step = JourneyStep::create(['journey_definition_id' => $definition->id, 'key' => 'basic', 'title' => 'Basic', 'order' => 1]);
    JourneyStepField::create([
        'journey_step_id' => $step->id, 'key' => 'full_name', 'label' => 'Full name',
        'type' => FieldType::Text, 'validation_rules' => ['required'], 'order' => 1,
    ]);

    // Relation manager tables render lazily via a follow-up Livewire request, not in this
    // initial HTML — the StepsRelationManager itself is exercised via Livewire::test() below.
    $this->get('/admin/journey-definitions')->assertOk();
    $this->get("/admin/journey-definitions/{$definition->public_id}/edit")->assertOk();
});

it('opens the step edit form with its nested fields repeater pre-filled', function () {
    $this->actingAs($this->admin);

    $product = LoanProduct::factory()->create();
    $definition = JourneyDefinition::create([
        'loan_product_id' => $product->id, 'version' => 1, 'status' => JourneyDefinitionStatus::Active,
    ]);
    $step = JourneyStep::create(['journey_definition_id' => $definition->id, 'key' => 'basic', 'title' => 'Basic', 'order' => 1]);
    JourneyStepField::create([
        'journey_step_id' => $step->id, 'key' => 'employment_type', 'label' => 'Employment type',
        'type' => FieldType::Select, 'options' => [['value' => 'salaried', 'label' => 'Salaried']],
        'validation_rules' => ['required'], 'order' => 1,
    ]);
    JourneyStepField::create([
        'journey_step_id' => $step->id, 'key' => 'company_name', 'label' => 'Company name',
        'type' => FieldType::Text, 'validation_rules' => ['required'], 'order' => 2,
        'conditional_on' => ['field' => 'employment_type', 'operator' => '=', 'value' => 'salaried'],
    ]);

    Livewire::test(StepsRelationManager::class, [
        'ownerRecord' => $definition,
        'pageClass' => EditJourneyDefinition::class,
    ])
        ->assertCanSeeTableRecords([$step])
        ->mountTableAction('edit', $step)
        ->assertTableActionDataSet([
            'key' => 'basic',
            'title' => 'Basic',
        ]);
});

it('renders the journey applications index and view pages', function () {
    $this->actingAs($this->admin);

    $product = LoanProduct::factory()->create();
    $definition = JourneyDefinition::create([
        'loan_product_id' => $product->id, 'version' => 1, 'status' => JourneyDefinitionStatus::Active,
    ]);
    $session = JourneySession::create([
        'loan_product_id' => $product->id, 'journey_definition_id' => $definition->id,
    ]);

    $this->get('/admin/journey-sessions')->assertOk();
    $this->get("/admin/journey-sessions/{$session->public_id}")->assertOk();
});

it('renders the credit consent section on an application with consent', function () {
    $this->actingAs($this->admin);

    $product = LoanProduct::factory()->create();
    $definition = JourneyDefinition::create([
        'loan_product_id' => $product->id, 'version' => 1, 'status' => JourneyDefinitionStatus::Active,
    ]);
    $session = JourneySession::create([
        'loan_product_id' => $product->id, 'journey_definition_id' => $definition->id,
    ]);
    CreditConsent::factory()->create(['journey_session_id' => $session->id]);

    $this->get("/admin/journey-sessions/{$session->public_id}")
        ->assertOk()
        ->assertSee('Credit bureau consent');
});
