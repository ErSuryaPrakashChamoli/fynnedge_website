<?php

use App\Support\Localization\ContentTranslations;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Gives the existing content its Hindi from database/seeders/data/translations/hi.
     * Deploys only migrate, so this is how a live database gets it. Each value
     * is matched by its exact English text: content an admin has reworded since
     * keeps showing English to Hindi visitors until someone translates it in
     * the admin, and Hindi already entered there is never overwritten. See
     * App\Support\Localization\ContentTranslations.
     */
    public function up(): void
    {
        Log::info('Filled Hindi translations for existing content.', ContentTranslations::apply('hi'));
    }

    /**
     * Nothing to undo separately: rolling back the migration that added the
     * `_hi` columns removes the translations with them, and clearing them here
     * would also wipe Hindi that editors typed in the admin.
     */
    public function down(): void
    {
        //
    }
};
