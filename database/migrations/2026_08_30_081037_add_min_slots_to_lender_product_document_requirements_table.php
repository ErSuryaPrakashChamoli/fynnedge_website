<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('lender_product_document_requirements', function (Blueprint $table) {
            $table->unsignedInteger('min_slots')->default(1)->after('is_required');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lender_product_document_requirements', function (Blueprint $table) {
            $table->dropColumn('min_slots');
        });
    }
};
