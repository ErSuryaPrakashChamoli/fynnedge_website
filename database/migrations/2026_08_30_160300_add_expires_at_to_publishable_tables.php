<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional expiry for every model using the Publishable trait. Paired with
     * published_at (already live-evaluated in scopePublished()), this makes
     * scheduled publish AND scheduled expiry work with zero cron/job — both are
     * plain "is now() past this timestamp" checks evaluated on every request.
     */
    private array $tables = ['loan_products', 'articles', 'pages', 'testimonials', 'loan_landing_pages'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->timestamp('expires_at')->nullable()->after('published_at');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('expires_at');
            });
        }
    }
};
