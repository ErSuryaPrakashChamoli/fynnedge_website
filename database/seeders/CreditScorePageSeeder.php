<?php

namespace Database\Seeders;

use App\Models\CreditScorePage;
use App\Modules\CreditScore\Enums\BureauName;
use Illuminate\Database\Seeder;

/**
 * One empty row per bureau page, so Content → Credit Score Pages lists every
 * page ready to edit. Empty rows change nothing on the public site — a blank
 * body hides the "About" section — and a row that already exists is left
 * untouched, so re-running this never overwrites the marketing team's copy.
 */
class CreditScorePageSeeder extends Seeder
{
    public function run(): void
    {
        foreach (BureauName::cases() as $bureau) {
            CreditScorePage::query()->firstOrCreate(['bureau' => $bureau->value]);
        }
    }
}
