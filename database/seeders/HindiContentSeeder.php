<?php

namespace Database\Seeders;

use App\Support\Localization\ContentTranslations;
use Illuminate\Database\Seeder;

/**
 * Fills the Hindi columns of freshly seeded content from
 * database/seeders/data/translations/hi. Run it last: it only matches content
 * that already exists. Run it again after re-running a single content seeder
 * on its own (e.g. LoanLocationPageSeeder):
 * `php artisan db:seed --class=HindiContentSeeder --force`.
 */
class HindiContentSeeder extends Seeder
{
    public function run(): void
    {
        ContentTranslations::apply('hi');
    }
}
