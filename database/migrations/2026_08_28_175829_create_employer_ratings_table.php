<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employer_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lender_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employer_category_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['employer_id', 'lender_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employer_ratings');
    }
};
