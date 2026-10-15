<?php

use App\Support\Localization\ContentTranslations;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Applies the translation files again for content added after the first
     * Hindi backfill (newer rate notes and social videos). Same rules as that
     * backfill: only blank Hindi, only where the English still matches.
     * Content saved from now on is translated automatically (AutoTranslation).
     */
    public function up(): void
    {
        Log::info('Filled Hindi translations for recently added content.', ContentTranslations::apply('hi'));
    }

    public function down(): void
    {
        //
    }
};
