<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lender_product_document_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lender_product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_type_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_required')->default(true);
            $table->string('notes')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->unique(['lender_product_id', 'document_type_id'], 'lp_document_requirement_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lender_product_document_requirements');
    }
};
