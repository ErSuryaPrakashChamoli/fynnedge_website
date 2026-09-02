<?php

namespace Database\Seeders;

use App\Models\LoanProduct;
use App\Modules\Applications\Models\DocumentType;
use App\Modules\Applications\Models\LenderProductDocumentRequirement;
use Illuminate\Database\Seeder;

/**
 * Wires up the documents a customer must upload during the self-service
 * application flow, for the loan products that already have a seeded
 * LenderProduct (personal-loan, home-loan — see DatabaseSeeder/JourneySeeder).
 * Admins can add, remove or reorder these per lender product afterwards
 * through the Document Types and Document Requirements resources.
 */
class DocumentRequirementSeeder extends Seeder
{
    private const ADDRESS_PROOF_NOTES = 'Upload one: Aadhaar Card, Voter ID, Passport, Driving Licence, Utility Bill (electricity/water/gas), or Rent Agreement';

    /**
     * @var array<string, list<array{key: string, label: string, notes?: string, is_required?: bool, min_slots?: int, allow_multiple?: bool, allow_custom_label?: bool}>>
     */
    private const REQUIREMENTS_BY_LOAN_PRODUCT_SLUG = [
        'personal-loan' => [
            ['key' => 'pan_card', 'label' => 'PAN Card'],
            ['key' => 'aadhaar_card', 'label' => 'Aadhaar Card'],
            ['key' => 'address_proof', 'label' => 'Address Proof', 'notes' => self::ADDRESS_PROOF_NOTES],
            ['key' => 'bank_statement', 'label' => 'Bank Statement', 'notes' => 'Last 3 months'],
            ['key' => 'payslip', 'label' => 'Payslip', 'notes' => "Last 3 months' salary slips", 'min_slots' => 3],
            ['key' => 'photograph', 'label' => 'Photograph'],
            ['key' => 'other_document', 'label' => 'Other', 'notes' => 'Any other supporting document — name it before uploading', 'is_required' => false, 'allow_multiple' => true, 'allow_custom_label' => true],
        ],
        'home-loan' => [
            ['key' => 'pan_card', 'label' => 'PAN Card'],
            ['key' => 'aadhaar_card', 'label' => 'Aadhaar Card'],
            ['key' => 'address_proof', 'label' => 'Address Proof', 'notes' => self::ADDRESS_PROOF_NOTES],
            ['key' => 'bank_statement', 'label' => 'Bank Statement', 'notes' => 'Last 6 months'],
            ['key' => 'payslip', 'label' => 'Payslip', 'notes' => "Last 3 months' salary slips", 'min_slots' => 3],
            ['key' => 'itr', 'label' => 'Income Tax Returns', 'notes' => 'Last 2 years'],
            ['key' => 'property_documents', 'label' => 'Property Documents'],
            ['key' => 'photograph', 'label' => 'Photograph'],
            ['key' => 'other_document', 'label' => 'Other', 'notes' => 'Any other supporting document — name it before uploading', 'is_required' => false, 'allow_multiple' => true, 'allow_custom_label' => true],
        ],
    ];

    public function run(): void
    {
        foreach (self::REQUIREMENTS_BY_LOAN_PRODUCT_SLUG as $slug => $requirements) {
            $loanProduct = LoanProduct::query()->where('slug', $slug)->first();

            if (! $loanProduct) {
                continue;
            }

            foreach ($loanProduct->lenderProducts as $lenderProduct) {
                foreach ($requirements as $order => $requirement) {
                    $documentType = DocumentType::query()->updateOrCreate(
                        ['key' => $requirement['key']],
                        [
                            'label' => $requirement['label'],
                            'order' => $order,
                            'allow_multiple' => $requirement['allow_multiple'] ?? false,
                            'allow_custom_label' => $requirement['allow_custom_label'] ?? false,
                        ],
                    );

                    LenderProductDocumentRequirement::query()->updateOrCreate(
                        ['lender_product_id' => $lenderProduct->id, 'document_type_id' => $documentType->id],
                        [
                            'is_required' => $requirement['is_required'] ?? true,
                            'notes' => $requirement['notes'] ?? null,
                            'order' => $order,
                            'min_slots' => $requirement['min_slots'] ?? 1,
                        ],
                    );
                }
            }
        }
    }
}
