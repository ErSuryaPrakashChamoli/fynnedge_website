<?php

namespace Database\Seeders;

use App\Enums\LenderStatus;
use App\Enums\LenderType;
use App\Models\Lender;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LenderRosterSeeder extends Seeder
{
    /**
     * Seed a broad roster of real, well-known Indian banks and NBFCs/HFCs so
     * admins have less typing to do when attaching lender offers. This only
     * seeds Lender identity rows (name/slug/type) — no LenderProduct offer
     * terms are fabricated for real institutions; admins add real
     * commercial terms and eligibility criteria via the admin UI or the
     * "Lender Offers" CSV import once they have them.
     */
    public function run(): void
    {
        $lenders = [
            // Public sector banks
            ['name' => 'State Bank of India', 'type' => LenderType::Bank],
            ['name' => 'Punjab National Bank', 'type' => LenderType::Bank],
            ['name' => 'Bank of Baroda', 'type' => LenderType::Bank],
            ['name' => 'Canara Bank', 'type' => LenderType::Bank],
            ['name' => 'Union Bank of India', 'type' => LenderType::Bank],
            ['name' => 'Bank of India', 'type' => LenderType::Bank],
            ['name' => 'Indian Bank', 'type' => LenderType::Bank],
            ['name' => 'Central Bank of India', 'type' => LenderType::Bank],

            // Private sector banks
            ['name' => 'Kotak Mahindra Bank', 'type' => LenderType::Bank],
            ['name' => 'IndusInd Bank', 'type' => LenderType::Bank],
            ['name' => 'Yes Bank', 'type' => LenderType::Bank],
            ['name' => 'IDFC FIRST Bank', 'type' => LenderType::Bank],
            ['name' => 'Federal Bank', 'type' => LenderType::Bank],
            ['name' => 'RBL Bank', 'type' => LenderType::Bank],
            ['name' => 'South Indian Bank', 'type' => LenderType::Bank],
            ['name' => 'Karur Vysya Bank', 'type' => LenderType::Bank],
            ['name' => 'City Union Bank', 'type' => LenderType::Bank],
            ['name' => 'DCB Bank', 'type' => LenderType::Bank],

            // NBFCs / HFCs
            ['name' => 'Bajaj Finance', 'type' => LenderType::Nbfc],
            ['name' => 'Tata Capital', 'type' => LenderType::Nbfc],
            ['name' => 'HDB Financial Services', 'type' => LenderType::Nbfc],
            ['name' => 'Muthoot Finance', 'type' => LenderType::Nbfc],
            ['name' => 'L&T Finance', 'type' => LenderType::Nbfc],
            ['name' => 'Cholamandalam Finance', 'type' => LenderType::Nbfc],
            ['name' => 'Piramal Finance', 'type' => LenderType::Nbfc],
            ['name' => 'Aditya Birla Finance', 'type' => LenderType::Nbfc],
            ['name' => 'Poonawalla Fincorp', 'type' => LenderType::Nbfc],
            ['name' => 'Hero FinCorp', 'type' => LenderType::Nbfc],
            ['name' => 'IIFL Finance', 'type' => LenderType::Nbfc],
            ['name' => 'Manappuram Finance', 'type' => LenderType::Nbfc],
            ['name' => 'PNB Housing Finance', 'type' => LenderType::Nbfc],
        ];

        foreach ($lenders as $entry) {
            Lender::query()->firstOrCreate(
                ['slug' => Str::slug($entry['name'])],
                [
                    'name' => $entry['name'],
                    'type' => $entry['type'],
                    'status' => LenderStatus::Active,
                ],
            );
        }
    }
}
