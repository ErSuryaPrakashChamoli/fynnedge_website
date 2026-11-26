<?php

use App\Models\Lender;
use App\Models\LenderProduct;
use App\Models\Setting;
use App\Models\User;
use App\Modules\Applications\Enums\DocumentStatus;
use App\Modules\Applications\Models\DocumentType;
use App\Modules\Customers\Models\Customer;
use App\Modules\DebtConsolidation\Actions\ChangeDebtApplicationStatus;
use App\Modules\DebtConsolidation\Actions\CreateLenderApplication;
use App\Modules\DebtConsolidation\Actions\GenerateDebtReport;
use App\Modules\DebtConsolidation\Actions\MatchLenders;
use App\Modules\DebtConsolidation\Actions\RecordClosurePayment;
use App\Modules\DebtConsolidation\Actions\RecordDebtConsent;
use App\Modules\DebtConsolidation\Actions\ReviewDebtDocument;
use App\Modules\DebtConsolidation\Actions\UpdateClosureStatus;
use App\Modules\DebtConsolidation\Actions\UpdateLenderApplicationStatus;
use App\Modules\DebtConsolidation\Actions\UploadDebtDocument;
use App\Modules\DebtConsolidation\Enums\ActorType;
use App\Modules\DebtConsolidation\Enums\ClosureStatus;
use App\Modules\DebtConsolidation\Enums\ConsentPurpose;
use App\Modules\DebtConsolidation\Enums\DebtApplicationStatus;
use App\Modules\DebtConsolidation\Enums\LenderApplicationStatus;
use App\Modules\DebtConsolidation\Enums\LenderMatchStatus;
use App\Modules\DebtConsolidation\Enums\ReportType;
use App\Modules\DebtConsolidation\Models\DebtApplication;
use App\Modules\DebtConsolidation\Models\DebtClosure;
use App\Modules\DebtConsolidation\Models\DebtFinancialProfile;
use App\Modules\DebtConsolidation\Models\DebtForeclosureQuotation;
use App\Modules\DebtConsolidation\Models\DebtLenderPolicy;
use App\Modules\DebtConsolidation\Models\DebtLiability;
use App\Modules\DebtConsolidation\Notifications\DebtCaseNotifier;
use App\Modules\DebtConsolidation\Notifications\DebtCaseStaffAlert;
use App\Modules\DebtConsolidation\Notifications\DebtCaseUpdate;
use App\Modules\DebtConsolidation\Services\DebtConsolidationSettings;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Storage::fake('local');
    $this->staff = User::factory()->create(['is_admin' => true]);
});

function assessedCase(): DebtApplication
{
    $application = DebtApplication::factory()->submitted()->create();
    DebtFinancialProfile::factory()->for($application, 'application')->create();
    DebtLiability::factory()->for($application, 'application')->create();
    DebtLiability::factory()->for($application, 'application')->creditCard()->create();

    return $application;
}

/**
 * assessedCase() opened from a portal customer's verified mobile.
 */
function ownedAssessedCase(): DebtApplication
{
    $customer = Customer::factory()->portalAccount('9811100009')->create();
    $application = assessedCase();
    $application->forceFill(['customer_id' => $customer->id, 'mobile' => $customer->verified_mobile])->save();

    return $application->fresh();
}

function policyFor(array $overrides = []): DebtLenderPolicy
{
    return DebtLenderPolicy::factory()->create($overrides);
}

describe('lender matching', function () {
    it('explains a potential match and records the indicative loan', function () {
        $application = assessedCase();
        $policy = policyFor();

        $match = app(MatchLenders::class)->handle($application, $this->staff->id)->sole();

        expect($match->match_status)->toBe(LenderMatchStatus::PotentialMatch)
            ->and($match->lender_product_id)->toBe($policy->lender_product_id)
            ->and((float) $match->indicative_loan_amount)->toBeGreaterThan(650000.0)
            ->and(collect($match->reasons)->every(fn (array $reason): bool => $reason['passed']))->toBeTrue();
    });

    it('does not match a lender that refuses one of the debts, and says which', function () {
        $application = assessedCase();
        policyFor(['accepted_liability_types' => ['personal_loan']]);

        $match = app(MatchLenders::class)->handle($application, $this->staff->id)->sole();

        expect($match->match_status)->toBe(LenderMatchStatus::NotMatched)
            ->and(collect($match->failedReasons())->pluck('message')->implode(' '))->toContain('Credit card outstanding');
    });

    it('asks for missing information instead of guessing', function () {
        $application = assessedCase();
        $application->financialProfile->update(['declared_credit_score' => null]);
        policyFor();

        expect(app(MatchLenders::class)->handle($application, $this->staff->id)->sole())
            ->match_status->toBe(LenderMatchStatus::NeedsInformation)
            ->missing_information->toContain('Credit score');
    });

    it('ignores draft, expired and inactive-lender policies', function () {
        $application = assessedCase();
        policyFor(['status' => 'draft']);
        policyFor(['effective_until' => now()->subDay()->toDateString()]);
        policyFor(['lender_product_id' => LenderProduct::factory()->for(Lender::factory()->state(['status' => 'inactive']))]);

        expect(app(MatchLenders::class)->handle($application, $this->staff->id))->toBeEmpty();
    });
});

describe('lender applications', function () {
    it('cannot be opened without the customer consenting to that lender', function () {
        $application = assessedCase();

        app(CreateLenderApplication::class)->handle($application, LenderProduct::factory()->create(), 650000, $this->staff->id);
    })->throws(ValidationException::class);

    it('records the lender decision with evidence and opens closure tracking on disbursement without closing any loan', function () {
        $application = assessedCase();
        $product = LenderProduct::factory()->create();
        app(RecordDebtConsent::class)->handle($application, ConsentPurpose::LenderSharing, lenderProduct: $product);
        $lenderApplication = app(CreateLenderApplication::class)->handle($application, $product, 661713, $this->staff->id);
        $update = app(UpdateLenderApplicationStatus::class);

        expect(fn () => $update->handle($lenderApplication, LenderApplicationStatus::Approved, $this->staff->id, 'Lender email'))->toThrow(ValidationException::class);

        $update->handle($lenderApplication, LenderApplicationStatus::Submitted, $this->staff->id);
        expect(fn () => $update->handle($lenderApplication->fresh(), LenderApplicationStatus::Approved, $this->staff->id))->toThrow(ValidationException::class);
        $update->handle($lenderApplication->fresh(), LenderApplicationStatus::Approved, $this->staff->id, 'Approval email from lender, 25 Nov');
        expect(fn () => $update->handle($lenderApplication->fresh(), LenderApplicationStatus::Disbursed, $this->staff->id))->toThrow(ValidationException::class);
        $update->handle($lenderApplication->fresh(), LenderApplicationStatus::Disbursed, $this->staff->id, null, ['disbursed_amount' => 661713, 'disbursed_at' => now()]);

        expect($application->fresh()->status)->toBe(DebtApplicationStatus::ClosureInProgress)
            ->and($application->closures()->count())->toBe(2)
            ->and($application->closures()->pluck('status')->unique()->all())->toBe([ClosureStatus::Planned])
            ->and($lenderApplication->statusChanges()->count())->toBe(4);
    });

    it('refuses to change a rejected application', function () {
        $application = assessedCase();
        $product = LenderProduct::factory()->create();
        app(RecordDebtConsent::class)->handle($application, ConsentPurpose::LenderSharing, lenderProduct: $product);
        $lenderApplication = app(CreateLenderApplication::class)->handle($application, $product, 600000, $this->staff->id);
        $update = app(UpdateLenderApplicationStatus::class);
        $update->handle($lenderApplication, LenderApplicationStatus::Submitted, $this->staff->id);
        $update->handle($lenderApplication->fresh(), LenderApplicationStatus::Rejected, $this->staff->id, null, ['rejection_reason' => 'FOIR above policy']);

        $update->handle($lenderApplication->fresh(), LenderApplicationStatus::Approved, $this->staff->id, 'changed mind');
    })->throws(ValidationException::class);
});

describe('closures', function () {
    it('verifies a full closure only after a confirmed payment and a verified NOC, then completes the case', function () {
        $application = assessedCase();
        $liability = $application->liabilities()->first();
        $application->liabilities()->whereKeyNot($liability->id)->delete();
        // Closures exist once the new loan is disbursed, which moves the case to closure.
        $application->forceFill(['status' => DebtApplicationStatus::ClosureInProgress])->save();
        $closure = DebtClosure::factory()->for($liability, 'liability')->create(['debt_application_id' => $application->id, 'final_closure_amount' => 500000]);
        $status = app(UpdateClosureStatus::class);
        $payments = app(RecordClosurePayment::class);

        expect(fn () => $status->handle($closure, ClosureStatus::PaymentConfirmed, $this->staff->id))->toThrow(ValidationException::class);

        $payment = $payments->record($closure, ['amount' => 500000, 'paid_on' => now()->toDateString(), 'payment_mode' => 'lender_direct', 'paid_by' => 'new_lender', 'utr_reference' => 'UTR123'], $this->staff->id);
        $payments->confirm($payment, $this->staff->id);
        $status->handle($closure->fresh(), ClosureStatus::PaymentConfirmed, $this->staff->id);

        expect(fn () => $status->handle($closure->fresh(), ClosureStatus::ClosureVerified, $this->staff->id))->toThrow(ValidationException::class);

        $noc = app(UploadDebtDocument::class)->handle($application, UploadedFile::fake()->createWithContent('noc.pdf', '%PDF-1.4 noc'), DocumentType::query()->where('key', 'noc_closure_letter')->sole(), ActorType::Staff, $this->staff->id, 'NOC', closureId: $closure->id);
        $status->handle($closure->fresh(), ClosureStatus::NocReceived, $this->staff->id, noc: $noc);
        expect(fn () => $status->handle($closure->fresh(), ClosureStatus::ClosureVerified, $this->staff->id))->toThrow(ValidationException::class);

        app(ReviewDebtDocument::class)->verify($noc, $this->staff->id);
        $status->handle($closure->fresh(), ClosureStatus::ClosureVerified, $this->staff->id);

        expect($closure->fresh())->status->toBe(ClosureStatus::ClosureVerified)->verified_by->toBe($this->staff->id)
            ->and($application->fresh()->status)->toBe(DebtApplicationStatus::Completed);
    });

    it('verifies a partial closure on confirmed payment alone', function () {
        $application = assessedCase();
        $liability = $application->liabilities()->first();
        $closure = DebtClosure::factory()->for($liability, 'liability')->create(['debt_application_id' => $application->id, 'closure_type' => 'partial', 'final_closure_amount' => 100000]);
        $payment = app(RecordClosurePayment::class)->record($closure, ['amount' => 100000, 'paid_on' => now()->toDateString(), 'payment_mode' => 'neft', 'paid_by' => 'customer', 'utr_reference' => 'N123'], $this->staff->id);
        app(RecordClosurePayment::class)->confirm($payment, $this->staff->id);

        app(UpdateClosureStatus::class)->handle($closure->fresh(), ClosureStatus::ClosureVerified, $this->staff->id);

        expect($closure->fresh()->status)->toBe(ClosureStatus::ClosureVerified);
    });

    it('will not confirm a payment without a UTR or proof', function () {
        $closure = DebtClosure::factory()->create();
        $payment = app(RecordClosurePayment::class)->record($closure, ['amount' => 10, 'paid_on' => now()->toDateString(), 'payment_mode' => 'neft', 'paid_by' => 'customer'], $this->staff->id);

        app(RecordClosurePayment::class)->confirm($payment, $this->staff->id);
    })->throws(ValidationException::class);
});

describe('documents', function () {
    it('stores uploads encrypted with their hash and keeps earlier versions', function () {
        $application = assessedCase();
        $type = DocumentType::query()->where('key', 'loan_statement')->sole();
        $upload = app(UploadDebtDocument::class);

        $first = $upload->handle($application, UploadedFile::fake()->createWithContent('statement.pdf', '%PDF-1.4 first'), $type, ActorType::Customer);
        $second = $upload->handle($application, UploadedFile::fake()->createWithContent('statement.pdf', '%PDF-1.4 second'), null, ActorType::Customer, replaces: $first);

        $stored = Storage::disk('local')->get($first->path);
        expect($stored)->not->toContain('first')
            ->and(Crypt::decryptString($stored))->toBe('%PDF-1.4 first')
            ->and($first->sha256)->toBe(hash('sha256', '%PDF-1.4 first'))
            ->and($second)->version->toBe(2)->supersedes_id->toBe($first->id)->document_type_id->toBe($type->id)
            ->and($first->fresh()->isCurrent())->toBeFalse()
            ->and($second->contents())->toBe('%PDF-1.4 second');
    });

    it('refuses files that are not PDF or images, whatever their name', function () {
        app(UploadDebtDocument::class)->handle(assessedCase(), UploadedFile::fake()->createWithContent('statement.pdf', '<?php echo 1;'), null, ActorType::Customer);
    })->throws(ValidationException::class);

    it('records a rejection with the reason the customer will see', function () {
        $document = app(UploadDebtDocument::class)->handle(assessedCase(), UploadedFile::fake()->createWithContent('s.pdf', '%PDF-1.4 statement'), null, ActorType::Customer);

        app(ReviewDebtDocument::class)->reject($document, $this->staff->id, 'Statement is older than 3 months');

        expect($document->fresh())->status->toBe(DocumentStatus::Rejected)->rejection_reason->toBe('Statement is older than 3 months');
    });
});

describe('reports', function () {
    it('generates every report type as an encrypted, versioned PDF', function (ReportType $type) {
        $application = assessedCase();

        $report = app(GenerateDebtReport::class)->handle($application, $type, $this->staff->id);
        $again = app(GenerateDebtReport::class)->handle($application, $type, $this->staff->id);

        expect($report->contents())->toStartWith('%PDF')
            ->and(hash('sha256', $report->contents()))->toBe($report->sha256)
            ->and($again->version)->toBe(2)
            ->and($report->debt_calculation_snapshot_id !== null)->toBe($type->needsSnapshot());
    })->with(ReportType::cases());

    it('never releases the internal advisor assessment to the customer', function () {
        app(GenerateDebtReport::class)->handle(assessedCase(), ReportType::AdvisorAssessment, $this->staff->id, customerVisible: true);
    })->throws(ValidationException::class);
});

describe('case status', function () {
    it('needs a reason to close a case and never sets Completed by hand', function () {
        $application = assessedCase();
        $change = app(ChangeDebtApplicationStatus::class);

        expect(fn () => $change->handle($application, DebtApplicationStatus::NotProceeding, $this->staff->id))->toThrow(ValidationException::class)
            ->and(fn () => $change->handle($application, DebtApplicationStatus::Completed, $this->staff->id, 'done'))->toThrow(ValidationException::class);

        $change->handle($application, DebtApplicationStatus::Contacted, $this->staff->id);

        expect($application->fresh()->status)->toBe(DebtApplicationStatus::Contacted)
            ->and($application->statusChanges()->first()->to_status)->toBe('contacted');
    });
});

describe('acceptance fixes', function () {
    it('still records what the lender decided after the customer withdraws consent, and tells the team', function () {
        Notification::fake();
        Setting::set(DebtConsolidationSettings::KEY, ['staff_notification_email' => 'team@example.com']);
        $application = ownedAssessedCase();
        $product = LenderProduct::factory()->create();
        $consent = app(RecordDebtConsent::class)->handle($application, ConsentPurpose::LenderSharing, lenderProduct: $product);
        $lenderApplication = app(CreateLenderApplication::class)->handle($application, $product, 600000, $this->staff->id);
        $update = app(UpdateLenderApplicationStatus::class);
        $update->handle($lenderApplication, LenderApplicationStatus::Submitted, $this->staff->id);

        $this->actingAs($application->customer, 'customer')
            ->post(route('portal.debt.consents.withdraw', [$application, $consent->public_id]))
            ->assertRedirect();
        $update->handle($lenderApplication->fresh(), LenderApplicationStatus::Rejected, $this->staff->id, null, ['rejection_reason' => 'FOIR above policy']);

        expect($lenderApplication->fresh()->status)->toBe(LenderApplicationStatus::Rejected);
        Notification::assertSentOnDemand(DebtCaseStaffAlert::class, fn (DebtCaseStaffAlert $alert, array $channels, object $notifiable): bool => str_contains($alert->summary, 'withdrew') && $notifiable->routes['mail'] === ['team@example.com']);
    });

    it('links every email to the site address, never to the host a visitor\'s request names', function () {
        Notification::fake();
        Setting::set(DebtConsolidationSettings::KEY, ['staff_notification_email' => 'team@example.com']);
        $application = ownedAssessedCase();
        URL::setRequest(Request::create('http://look-alike.example/debt-consolidation/assessment', 'POST'));

        app(DebtCaseNotifier::class)->enquiryReceived($application);

        $site = rtrim((string) config('app.url'), '/').'/';
        Notification::assertSentOnDemand(DebtCaseStaffAlert::class, fn (DebtCaseStaffAlert $alert): bool => str_starts_with($alert->url, $site));
        Notification::assertSentTo($application->customer, DebtCaseUpdate::class, fn (DebtCaseUpdate $update): bool => str_starts_with((string) $update->url, $site));
    });

    it('keeps the customer\'s email address encrypted while their case update waits in the queue', function () {
        config(['queue.default' => 'database']);
        $application = ownedAssessedCase();
        $application->forceFill(['email' => 'customer.private@example.com'])->save();

        app(DebtCaseNotifier::class)->enquiryReceived($application);

        $payloads = DB::table('jobs')->pluck('payload');
        expect($payloads)->not->toBeEmpty()
            ->and($payloads->implode(''))->not->toContain('customer.private@example.com');
    });

    it('confirms a closure payment only against a known amount, including a quotation recorded after the closure opened', function () {
        $application = assessedCase();
        $liability = $application->liabilities()->first();
        $closure = DebtClosure::factory()->for($liability, 'liability')->create(['debt_application_id' => $application->id]);
        $payments = app(RecordClosurePayment::class);
        $status = app(UpdateClosureStatus::class);
        $payments->confirm($payments->record($closure, ['amount' => 1, 'paid_on' => now()->toDateString(), 'payment_mode' => 'lender_direct', 'paid_by' => 'new_lender', 'utr_reference' => 'UTR1'], $this->staff->id), $this->staff->id);

        expect(fn () => $status->handle($closure->fresh(), ClosureStatus::PaymentConfirmed, $this->staff->id))
            ->toThrow(ValidationException::class, 'final closure amount');

        DebtForeclosureQuotation::factory()->for($liability, 'liability')->create(['total_amount' => 515000]);

        expect(fn () => $status->handle($closure->fresh(), ClosureStatus::PaymentConfirmed, $this->staff->id))
            ->toThrow(ValidationException::class, 'do not cover');

        $payments->confirm($payments->record($closure, ['amount' => 514999, 'paid_on' => now()->toDateString(), 'payment_mode' => 'lender_direct', 'paid_by' => 'new_lender', 'utr_reference' => 'UTR2'], $this->staff->id), $this->staff->id);
        $status->handle($closure->fresh(), ClosureStatus::PaymentConfirmed, $this->staff->id);

        expect($closure->fresh())->status->toBe(ClosureStatus::PaymentConfirmed)->debt_foreclosure_quotation_id->not->toBeNull();
    });

    it('checks a part-payment against its own amount, not the full foreclosure quotation', function () {
        $application = assessedCase();
        $liability = $application->liabilities()->first();
        DebtForeclosureQuotation::factory()->for($liability, 'liability')->create(['total_amount' => 515000]);
        $closure = DebtClosure::factory()->for($liability, 'liability')->create(['debt_application_id' => $application->id, 'closure_type' => 'partial', 'final_closure_amount' => 100000]);
        $payments = app(RecordClosurePayment::class);
        $payments->confirm($payments->record($closure, ['amount' => 100000, 'paid_on' => now()->toDateString(), 'payment_mode' => 'lender_direct', 'paid_by' => 'new_lender', 'utr_reference' => 'UTR3'], $this->staff->id), $this->staff->id);

        app(UpdateClosureStatus::class)->handle($closure->fresh(), ClosureStatus::PaymentConfirmed, $this->staff->id);

        expect($closure->fresh()->status)->toBe(ClosureStatus::PaymentConfirmed);
    });

    it('accepts as NOC only an NOC for this loan that no other closure uses', function () {
        $application = assessedCase();
        [$loan, $card] = $application->liabilities()->get()->all();
        $closure = DebtClosure::factory()->for($loan, 'liability')->create(['debt_application_id' => $application->id]);
        $other = DebtClosure::factory()->for($card, 'liability')->create(['debt_application_id' => $application->id]);
        $upload = fn (string $type, ?int $liabilityId) => app(UploadDebtDocument::class)->handle($application, UploadedFile::fake()->createWithContent('doc.pdf', '%PDF-1.4 doc'), DocumentType::query()->where('key', $type)->sole(), ActorType::Staff, $this->staff->id, 'Doc', liabilityId: $liabilityId);
        $status = app(UpdateClosureStatus::class);

        expect(fn () => $status->handle($closure, ClosureStatus::Planned, $this->staff->id, noc: $upload('loan_statement', $loan->id)))->toThrow(ValidationException::class, 'NOC / closure letter')
            ->and(fn () => $status->handle($closure, ClosureStatus::Planned, $this->staff->id, noc: $upload('noc_closure_letter', $card->id)))->toThrow(ValidationException::class, 'another loan');

        $noc = $upload('noc_closure_letter', null);
        $status->handle($other, ClosureStatus::Planned, $this->staff->id, noc: $noc);

        expect(fn () => $status->handle($closure, ClosureStatus::Planned, $this->staff->id, noc: $noc))->toThrow(ValidationException::class, 'another closure');
    });

    it('completes only a case in closure, tells the customer, and leaves a withdrawn case as staff left it', function (DebtApplicationStatus $caseStatus, DebtApplicationStatus $expected, bool $customerTold) {
        Notification::fake();
        $application = ownedAssessedCase();
        $liability = $application->liabilities()->first();
        $application->liabilities()->whereKeyNot($liability->id)->delete();
        $application->forceFill(['status' => $caseStatus])->save();
        $closure = DebtClosure::factory()->for($liability, 'liability')->create(['debt_application_id' => $application->id, 'closure_type' => 'partial', 'final_closure_amount' => 100000]);
        $payments = app(RecordClosurePayment::class);
        $payments->confirm($payments->record($closure, ['amount' => 100000, 'paid_on' => now()->toDateString(), 'payment_mode' => 'lender_direct', 'paid_by' => 'new_lender', 'utr_reference' => 'UTR4'], $this->staff->id), $this->staff->id);

        app(UpdateClosureStatus::class)->handle($closure->fresh(), ClosureStatus::ClosureVerified, $this->staff->id);

        expect($application->fresh()->status)->toBe($expected);

        $told = fn (DebtCaseUpdate $update): bool => $update->message === 'The status of your debt consolidation case is now: :status.' && $update->parameters['status'] === 'Completed';
        $customerTold
            ? Notification::assertSentTo($application->customer, DebtCaseUpdate::class, $told)
            : Notification::assertNotSentTo($application->customer, DebtCaseUpdate::class, $told);
    })->with([
        'in closure' => [DebtApplicationStatus::ClosureInProgress, DebtApplicationStatus::Completed, true],
        'withdrawn by staff' => [DebtApplicationStatus::Withdrawn, DebtApplicationStatus::Withdrawn, false],
    ]);
});
