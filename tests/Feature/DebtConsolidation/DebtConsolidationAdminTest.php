<?php

use App\Filament\Pages\DebtConsolidationDashboard;
use App\Filament\Pages\DebtConsolidationPagesSettings;
use App\Filament\Pages\DebtConsolidationSettings;
use App\Filament\RelationManagers\FaqsRelationManager;
use App\Filament\Resources\DebtApplications\DebtApplicationResource;
use App\Filament\Resources\DebtApplications\Pages\CreateDebtApplication;
use App\Filament\Resources\DebtApplications\Pages\ViewDebtApplication;
use App\Filament\Resources\DebtApplications\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\DebtApplications\RelationManagers\LiabilitiesRelationManager;
use App\Filament\Resources\DebtClosures\Pages\ViewDebtClosure;
use App\Filament\Resources\DebtClosures\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\DebtLenderPolicies\DebtLenderPolicyResource;
use App\Filament\Resources\DebtLenderPolicies\Pages\CreateDebtLenderPolicy;
use App\Filament\Resources\DebtLenderPolicies\Pages\EditDebtLenderPolicy;
use App\Filament\Resources\DebtLenderPolicies\Pages\ListDebtLenderPolicies;
use App\Filament\Resources\DebtScoringConfigs\DebtScoringConfigResource;
use App\Filament\Resources\DebtScoringConfigs\Pages\ListDebtScoringConfigs;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Models\LenderProduct;
use App\Models\Page;
use App\Models\User;
use App\Modules\DebtConsolidation\Actions\AssessDebtApplication;
use App\Modules\DebtConsolidation\Actions\MatchLenders;
use App\Modules\DebtConsolidation\Actions\RecordClosurePayment;
use App\Modules\DebtConsolidation\Enums\ActorType;
use App\Modules\DebtConsolidation\Enums\ConsentPurpose;
use App\Modules\DebtConsolidation\Enums\DebtApplicationStatus;
use App\Modules\DebtConsolidation\Enums\LenderMatchStatus;
use App\Modules\DebtConsolidation\Enums\LenderPolicyStatus;
use App\Modules\DebtConsolidation\Enums\VerificationStatus;
use App\Modules\DebtConsolidation\Models\DebtApplication;
use App\Modules\DebtConsolidation\Models\DebtClosure;
use App\Modules\DebtConsolidation\Models\DebtConsent;
use App\Modules\DebtConsolidation\Models\DebtDocument;
use App\Modules\DebtConsolidation\Models\DebtFinancialProfile;
use App\Modules\DebtConsolidation\Models\DebtLenderPolicy;
use App\Modules\DebtConsolidation\Models\DebtLiability;
use App\Modules\DebtConsolidation\Models\DebtScoringConfig;
use App\Modules\DebtConsolidation\Services\DebtConsolidationRoles;
use App\Modules\DebtConsolidation\Services\DebtConsolidationSettings as Settings;
use BezhanSalleh\FilamentShield\Resources\Roles\Pages\EditRole;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function staffCase(): DebtApplication
{
    $application = DebtApplication::factory()->submitted()->create();
    DebtFinancialProfile::factory()->for($application, 'application')->create();
    DebtLiability::factory()->for($application, 'application')->create();
    DebtLiability::factory()->for($application, 'application')->creditCard()->create();

    return $application;
}

describe('as a super admin', function () {
    beforeEach(fn () => $this->actingAs(User::factory()->create(['is_admin' => true])));

    it('renders every tab of the 360° case view', function (string $tab, string $expected) {
        $application = staffCase();
        app(AssessDebtApplication::class)->handle($application, ActorType::Staff);

        $this->get(DebtApplicationResource::getUrl('view', ['record' => $application]).($tab === '' ? '' : '?relation='.$tab))
            ->assertOk()
            ->assertSee($application->reference)
            ->assertSee($expected);
    })->with([
        ['', 'Latest assessment'], ['customer', 'Employment and income'], ['liabilities', 'Existing liabilities'], ['documents', 'Documents'],
        ['assessment', 'Liabilities in this calculation'], ['suitability', 'Three separate answers'], ['savings', 'Existing versus proposed'],
        ['foreclosure', 'Closure requirement per liability'], ['matching', 'Lender matching'], ['lender-applications', 'Lender applications'],
        ['closure', 'Debt closure'], ['communication', 'Communication'], ['timeline', 'Everything that happened on this case'], ['audit', 'Audit history'],
        ['collateral', 'Collateral'], ['scenarios', 'Scenarios'], ['assessments', 'Calculation history'], ['consents', 'Consents'], ['reports', 'Reports'], ['status-history', 'Status history'],
    ]);

    it('tells the advisor when a case has not been assessed yet', function () {
        $this->get(DebtApplicationResource::getUrl('view', ['record' => staffCase()]).'?relation=savings')->assertSee('Not assessed yet');
    });

    it('recalculates, matches lenders and changes status from the case header', function () {
        $application = staffCase();
        DebtLenderPolicy::factory()->create();

        Livewire::test(ViewDebtApplication::class, ['record' => $application->public_id])
            ->callAction('assess')
            ->callAction('match')
            ->callAction('status', ['status' => DebtApplicationStatus::Contacted->value, 'note' => 'Called the customer']);

        expect($application->snapshots()->count())->toBe(2)
            ->and($application->lenderMatches()->count())->toBe(1)
            ->and($application->fresh()->status)->toBe(DebtApplicationStatus::Contacted);
    });

    it('lets an advisor correct a liability and keeps the change in the audit history', function () {
        $application = staffCase();
        $liability = $application->liabilities()->first();

        Livewire::test(LiabilitiesRelationManager::class, ['ownerRecord' => $application, 'pageClass' => ViewDebtApplication::class])
            ->callTableAction('verify', $liability, ['verification_status' => VerificationStatus::Discrepancy->value, 'note' => 'Statement shows 4.8 L'])
            ->callTableAction('edit', $liability, ['outstanding_principal' => 480000]);

        expect($liability->fresh())->outstanding_principal->toEqual('480000.00')->verification_status->toBe(VerificationStatus::Discrepancy)
            ->and($liability->auditLogs()->where('action', 'updated')->get()->pluck('changes')->flatMap(fn ($changes) => array_keys($changes))->all())->toContain('outstanding_principal');
    });

    it('opens a case for a caller with documented consent', function () {
        Livewire::test(CreateDebtApplication::class)
            ->fillForm(['applicant_type' => 'individual', 'full_name' => 'Walk In', 'mobile' => '9123456789', 'consent_confirmed' => true, 'consent_note' => 'Recorded call 25 Nov'])
            ->call('create')
            ->assertHasNoFormErrors();

        $application = DebtApplication::query()->sole();
        expect($application)->status->toBe(DebtApplicationStatus::EnquiryReceived)->customer_id->toBeNull()
            ->and($application->consents()->pluck('method')->unique()->all())->toBe([DebtConsent::METHOD_RECORDED_BY_STAFF]);
    });

    it('streams a document decrypted to staff and records the download', function () {
        Storage::fake('local');
        $application = staffCase();
        $document = DebtDocument::factory()->for($application, 'application')->withStoredFile('%PDF-1.4 statement')->create();

        Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $application, 'pageClass' => ViewDebtApplication::class])
            ->callTableAction('download', $document)
            ->assertFileDownloaded($document->downloadName(), '%PDF-1.4 statement');

        expect($application->auditLogs()->where('action', 'document_downloaded')->exists())->toBeTrue();
    });

    it('publishes reviewed starter FAQs in one step from the page\'s FAQ tab', function () {
        $page = Page::query()->where('slug', 'debt-consolidation-faqs')->sole();

        Livewire::test(FaqsRelationManager::class, ['ownerRecord' => $page, 'pageClass' => EditPage::class])
            ->callTableBulkAction('publish', $page->faqs()->limit(3)->get());

        expect($page->faqs()->published()->count())->toBe(3);
    });

    it('renders the dashboard, settings and every list screen', function () {
        $this->get(DebtConsolidationDashboard::getUrl())->assertOk()->assertSee('Total debt assessed');
        $this->get(DebtConsolidationSettings::getUrl())->assertOk();
        $this->get(DebtConsolidationPagesSettings::getUrl())->assertOk();
        $this->get(DebtLenderPolicyResource::getUrl('create'))->assertOk();
    });

    it('saves settings and bumps the consent version when consent wording changes', function () {
        Livewire::test(DebtConsolidationSettings::class)
            ->set('data.enabled', true)
            ->set('data.consent_contact', 'New contact wording.')
            ->call('save');

        expect(Settings::enabled())->toBeTrue()
            ->and(Settings::consentVersion())->toBe('2')
            ->and(Settings::consentText(ConsentPurpose::Contact))->toBe('New contact wording.');
    });

    it('activates a scoring configuration and retires the previous version', function () {
        $draft = DebtScoringConfig::factory()->create(['version' => '2', 'created_by' => User::factory()->create()->id]);

        Livewire::test(ListDebtScoringConfigs::class)->callTableAction('activate', $draft);

        expect($draft->fresh()->status)->toBe(LenderPolicyStatus::Active)
            ->and(DebtScoringConfig::query()->where('applicant_type', 'individual')->where('version', '1')->value('status'))->toBe(LenderPolicyStatus::Retired);
    });
});

describe('lender policy lifecycle', function () {
    it('creates a policy in the admin form, needs a second person to approve it, then matches with it', function () {
        $author = User::factory()->create(['is_admin' => true]);
        $author->syncRoles([]);
        $author->givePermissionTo(Permission::findOrCreate('ViewAny:DebtLenderPolicy'), Permission::findOrCreate('Create:DebtLenderPolicy'), Permission::findOrCreate('Approve:DebtLenderPolicy'), Permission::findOrCreate('Update:DebtLenderPolicy'));
        $product = LenderProduct::factory()->create();
        $this->actingAs($author);

        Livewire::test(CreateDebtLenderPolicy::class)
            ->fillForm([
                'lender_product_id' => $product->id, 'financing_route' => 'personal_loan', 'version' => 1,
                'applicant_types' => ['individual'], 'accepted_liability_types' => ['personal_loan', 'credit_card_outstanding'],
                'source_name' => 'Lender DSA note', 'source_received_on' => now()->toDateString(), 'effective_from' => now()->subDay()->toDateString(),
                'interest_rate_min' => 11, 'interest_rate_max' => 15, 'max_tenure_months' => 60,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $policy = DebtLenderPolicy::query()->sole();
        expect($policy->status)->toBe(LenderPolicyStatus::Draft)->and($policy->created_by)->toBe($author->id);

        Livewire::test(ListDebtLenderPolicies::class)->callTableAction('approve', $policy);
        expect($policy->fresh()->status)->toBe(LenderPolicyStatus::Draft);

        $approver = tap(User::factory()->create(['is_admin' => true]))->syncRoles([]);
        $approver->givePermissionTo('ViewAny:DebtLenderPolicy', 'Approve:DebtLenderPolicy', 'Update:DebtLenderPolicy');
        $this->actingAs($approver);
        Livewire::test(ListDebtLenderPolicies::class)->callTableAction('approve', $policy);

        expect($policy->fresh())->status->toBe(LenderPolicyStatus::Active)->approved_by->toBe($approver->id);

        $match = app(MatchLenders::class)->handle(staffCase(), $approver->id)->sole();
        expect($match->lender_product_id)->toBe($product->id)
            ->and($match->match_status)->toBe(LenderMatchStatus::PotentialMatch);
    });
});

describe('roles', function () {
    beforeEach(fn () => $this->seed(RoleSeeder::class));

    it('lets a Debt Advisor work cases but not approve lender policies or change scoring', function () {
        $advisor = tap(User::factory()->create(['is_admin' => true]))->syncRoles([DebtConsolidationRoles::ADVISOR_ROLE]);
        $application = staffCase();
        $policy = DebtLenderPolicy::factory()->draft()->create();
        $this->actingAs($advisor);

        $this->get(DebtApplicationResource::getUrl('view', ['record' => $application]))->assertOk();
        $this->get(DebtConsolidationDashboard::getUrl())->assertOk();
        $this->get(DebtLenderPolicyResource::getUrl('create'))->assertForbidden();
        $this->get(DebtScoringConfigResource::getUrl('create'))->assertForbidden();
        $this->get(DebtConsolidationSettings::getUrl())->assertForbidden();

        expect($advisor->can('Approve:DebtLenderPolicy'))->toBeFalse()
            ->and($advisor->can('delete', $application))->toBeFalse();
    });

    it('keeps Marketing out of cases but lets it reword the pages', function () {
        $marketing = tap(User::factory()->create(['is_admin' => true]))->syncRoles(['Marketing']);
        $this->actingAs($marketing);

        $this->get(DebtApplicationResource::getUrl('index'))->assertForbidden();
        $this->get(DebtConsolidationPagesSettings::getUrl())->assertOk();
    });
});

describe('acceptance fixes', function () {
    beforeEach(fn () => $this->seed(RoleSeeder::class));

    it('keeps every Debt Consolidation permission when a role is saved in the role editor', function () {
        $this->actingAs(tap(User::factory()->create(['is_admin' => true]))->syncRoles(['super_admin']));
        $checker = Role::findOrCreate('Debt Checker');
        $checker->givePermissionTo(collect(['ViewAny:DebtLenderPolicy', 'Approve:DebtLenderPolicy', 'Activate:DebtScoringConfig', 'Delete:DebtDocument'])->map(fn (string $name) => Permission::findOrCreate($name)));

        foreach ([Role::findByName(DebtConsolidationRoles::ADVISOR_ROLE), $checker] as $role) {
            $before = $role->permissions()->pluck('name')->sort()->values()->all();

            Livewire::test(EditRole::class, ['record' => $role->getKey()])->call('save')->assertHasNoErrors();

            expect($role->fresh()->permissions()->pluck('name')->sort()->values()->all())->toBe($before);
        }
    });

    it('refuses approval or activation by anyone who changed the draft, not only its author', function () {
        $author = User::factory()->create(['is_admin' => true]);
        $editor = tap(User::factory()->create(['is_admin' => true]))->syncRoles([]);
        $editor->givePermissionTo('ViewAny:DebtLenderPolicy', 'Update:DebtLenderPolicy', 'Approve:DebtLenderPolicy', 'ViewAny:DebtScoringConfig', 'Update:DebtScoringConfig', 'Activate:DebtScoringConfig');
        $policy = DebtLenderPolicy::factory()->draft()->create(['created_by' => $author->id]);
        $config = DebtScoringConfig::factory()->create(['version' => '2', 'created_by' => $author->id]);
        $this->actingAs($editor);
        $policy->update(['notes' => 'Raised the rate band']);
        $config->update(['notes' => 'Tightened FOIR']);

        Livewire::test(ListDebtLenderPolicies::class)->callTableAction('approve', $policy);
        Livewire::test(ListDebtScoringConfigs::class)->callTableAction('activate', $config);

        expect($policy->fresh()->status)->toBe(LenderPolicyStatus::Draft)
            ->and($config->fresh()->status)->toBe(LenderPolicyStatus::Draft);
    });

    it('never changes an approved policy, even from an edit page opened while it was a draft', function () {
        $editor = tap(User::factory()->create(['is_admin' => true]))->syncRoles([]);
        $editor->givePermissionTo('ViewAny:DebtLenderPolicy', 'View:DebtLenderPolicy', 'Update:DebtLenderPolicy');
        $policy = DebtLenderPolicy::factory()->draft()->create(['interest_rate_max' => 15]);
        $this->actingAs($editor);
        $page = Livewire::test(EditDebtLenderPolicy::class, ['record' => $policy->getRouteKey()]);

        DebtLenderPolicy::query()->whereKey($policy->id)->update(['status' => LenderPolicyStatus::Active]);
        $page->fillForm(['interest_rate_max' => 24])->call('save');

        expect((float) $policy->fresh()->interest_rate_max)->toBe(15.0)
            ->and(fn () => $policy->fresh()->update(['interest_rate_max' => 24]))->toThrow(LogicException::class);
    });

    it('lets only staff who may update a closure confirm its payment', function () {
        $application = staffCase();
        $closure = DebtClosure::factory()->for($application->liabilities()->first(), 'liability')->create(['debt_application_id' => $application->id]);
        $viewer = tap(User::factory()->create(['is_admin' => true]))->syncRoles([]);
        $payment = app(RecordClosurePayment::class)->record($closure, ['amount' => 1000, 'paid_on' => now()->toDateString(), 'payment_mode' => 'lender_direct', 'paid_by' => 'new_lender', 'utr_reference' => 'UTR9'], User::factory()->create()->id);
        $viewer->givePermissionTo('ViewAny:DebtClosure', 'View:DebtClosure');
        $this->actingAs($viewer);

        Livewire::test(PaymentsRelationManager::class, ['ownerRecord' => $closure, 'pageClass' => ViewDebtClosure::class])
            ->assertTableActionHidden('confirm', $payment);

        $viewer->givePermissionTo('Update:DebtClosure');

        Livewire::test(PaymentsRelationManager::class, ['ownerRecord' => $closure, 'pageClass' => ViewDebtClosure::class])
            ->assertTableActionVisible('confirm', $payment);
    });

    it('shows each case-page action only to staff allowed to create what it creates', function () {
        $application = staffCase();
        $staff = tap(User::factory()->create(['is_admin' => true]))->syncRoles([]);
        $staff->givePermissionTo('ViewAny:DebtApplication', 'View:DebtApplication', 'Update:DebtApplication');
        $this->actingAs($staff);

        Livewire::test(ViewDebtApplication::class, ['record' => $application->getRouteKey()])
            ->assertActionVisible('status')
            ->assertActionHidden('assess')
            ->assertActionHidden('match');

        $staff->givePermissionTo('Create:DebtCalculationSnapshot', 'Create:DebtLenderMatch');

        Livewire::test(ViewDebtApplication::class, ['record' => $application->getRouteKey()])
            ->assertActionVisible('assess')
            ->assertActionVisible('match');
    });
});
