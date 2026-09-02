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
        Schema::create('loan_landing_pages', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('loan_product_id')->constrained()->cascadeOnDelete();
            $table->string('group');
            $table->string('title');
            $table->string('slug')->unique();
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_landing_pages');
    }
};
