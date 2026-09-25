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
        Schema::table('calculator_pages', function (Blueprint $table) {
            $table->string('heading')->nullable()->after('calculator_key');
            $table->text('description')->nullable()->after('heading');
            $table->string('meta_title')->nullable()->after('description');
            $table->text('meta_description')->nullable()->after('meta_title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('calculator_pages', function (Blueprint $table) {
            $table->dropColumn(['heading', 'description', 'meta_title', 'meta_description']);
        });
    }
};
