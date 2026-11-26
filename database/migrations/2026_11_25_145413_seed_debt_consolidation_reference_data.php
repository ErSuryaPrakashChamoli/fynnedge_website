<?php

use App\Modules\DebtConsolidation\DataTransferObjects\ScoringConfig;
use App\Modules\DebtConsolidation\Enums\ApplicantType;
use App\Modules\DebtConsolidation\Services\DebtConsolidationRoles;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Reference data the Debt Consolidation module needs on every install
 * (deploys run migrations, not seeders):
 *  - document types for debt statements, foreclosure letters, NOCs and
 *    payment proofs (existing types such as bank statements and ITRs are
 *    reused, never duplicated);
 *  - the baseline suitability scoring configuration (version 1, active) for
 *    individuals and businesses;
 *  - the Debt Advisor role and permissions.
 * Additive and safe to re-run; down() removes only rows it added and nothing
 * that a case already uses.
 */
return new class extends Migration
{
    /**
     * @var array<string, array{label: string, label_hi: string, description: string, allow_multiple: bool}>
     */
    private const DOCUMENT_TYPES = [
        'loan_statement' => ['label' => 'Loan Statement', 'label_hi' => 'लोन स्टेटमेंट', 'description' => 'Latest statement or repayment schedule of an existing loan.', 'allow_multiple' => true],
        'credit_card_statement' => ['label' => 'Credit Card Statement', 'label_hi' => 'क्रेडिट कार्ड स्टेटमेंट', 'description' => 'Latest credit card statement showing the outstanding.', 'allow_multiple' => true],
        'foreclosure_letter' => ['label' => 'Foreclosure Letter', 'label_hi' => 'फोरक्लोज़र लेटर', 'description' => 'The existing lender\'s foreclosure / closure amount quotation.', 'allow_multiple' => true],
        'noc_closure_letter' => ['label' => 'NOC / Closure Letter', 'label_hi' => 'एनओसी / क्लोज़र लेटर', 'description' => 'No-objection certificate or closure letter from the existing lender.', 'allow_multiple' => true],
        'closure_payment_proof' => ['label' => 'Closure Payment Proof', 'label_hi' => 'क्लोज़र भुगतान प्रमाण', 'description' => 'Proof that a closure payment reached the existing lender (UTR, receipt).', 'allow_multiple' => true],
        'sanction_letter' => ['label' => 'Sanction Letter', 'label_hi' => 'सैंक्शन लेटर', 'description' => 'Sanction letter issued by the new lender.', 'allow_multiple' => true],
        'business_financials' => ['label' => 'Business Financial Statements', 'label_hi' => 'व्यवसाय के वित्तीय विवरण', 'description' => 'Audited or provisional balance sheet and profit & loss.', 'allow_multiple' => true],
        'gst_returns' => ['label' => 'GST Returns', 'label_hi' => 'जीएसटी रिटर्न', 'description' => 'Recent GST returns of the business.', 'allow_multiple' => true],
    ];

    public function up(): void
    {
        $order = (int) DB::table('document_types')->max('order');

        foreach (self::DOCUMENT_TYPES as $key => $type) {
            if (DB::table('document_types')->where('key', $key)->exists()) {
                continue;
            }

            DB::table('document_types')->insert([
                'public_id' => (string) Str::uuid(),
                'key' => $key,
                'label' => $type['label'],
                'label_hi' => $type['label_hi'],
                'description' => $type['description'],
                'allow_multiple' => $type['allow_multiple'],
                'order' => ++$order,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach (ApplicantType::cases() as $applicantType) {
            if (DB::table('debt_scoring_configs')->where('applicant_type', $applicantType->value)->where('version', '1')->exists()) {
                continue;
            }

            $defaults = ScoringConfig::defaults($applicantType);

            DB::table('debt_scoring_configs')->insert([
                'public_id' => (string) Str::uuid(),
                'applicant_type' => $applicantType->value,
                'version' => '1',
                'status' => 'active',
                'weights' => json_encode($defaults->weights),
                'thresholds' => json_encode($defaults->thresholds),
                'notes' => 'Baseline shipped with formula version 1.0.0: mandate weights; thresholds pending business sign-off (docs/debt-consolidation/06-financial-engine.md).',
                'activated_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DebtConsolidationRoles::grant();
    }

    public function down(): void
    {
        // Only types nothing uses: application documents and lender product
        // requirements cascade-delete with their document type.
        $used = collect(['debt_documents', 'application_documents', 'lender_product_document_requirements'])
            ->filter(fn (string $table): bool => Schema::hasTable($table))
            ->flatMap(fn (string $table) => DB::table($table)->whereNotNull('document_type_id')->distinct()->pluck('document_type_id'))
            ->unique()
            ->all();

        DB::table('document_types')
            ->whereIn('key', array_keys(self::DOCUMENT_TYPES))
            ->whereNotIn('id', $used)
            ->delete();

        // Scoring configs and permissions go with their tables / stay harmless; the
        // debt_scoring_configs table itself is dropped by its own migration.
    }
};
