<?php

use App\Models\LenderProduct;
use App\Modules\Applications\Models\DocumentType;
use Illuminate\Support\Facades\DB;

it('rolls back its document types without deleting one another module now uses', function () {
    $sanctionLetter = DocumentType::query()->where('key', 'sanction_letter')->sole();
    DB::table('lender_product_document_requirements')->insert([
        'lender_product_id' => LenderProduct::factory()->create()->id,
        'document_type_id' => $sanctionLetter->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    (require database_path('migrations/2026_11_25_145413_seed_debt_consolidation_reference_data.php'))->down();

    expect(DocumentType::query()->whereKey($sanctionLetter->id)->exists())->toBeTrue()
        ->and(DB::table('lender_product_document_requirements')->where('document_type_id', $sanctionLetter->id)->exists())->toBeTrue()
        ->and(DocumentType::query()->where('key', 'gst_returns')->exists())->toBeFalse();
});
