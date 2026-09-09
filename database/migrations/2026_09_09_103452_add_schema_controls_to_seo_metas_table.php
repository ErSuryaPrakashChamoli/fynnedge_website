<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_metas', function (Blueprint $table) {
            $table->string('page_type')->nullable()->after('robots');
            $table->foreignId('schema_template_id')
                ->nullable()
                ->after('page_type')
                ->constrained('schema_templates')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('seo_metas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('schema_template_id');
            $table->dropColumn('page_type');
        });
    }
};
