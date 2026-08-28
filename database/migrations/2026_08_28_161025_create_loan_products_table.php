<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_products', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category');
            $table->string('summary')->nullable();
            $table->longText('body')->nullable();
            $table->json('features')->nullable();
            $table->json('eligibility_points')->nullable();
            $table->json('documents_required')->nullable();
            $table->json('process_steps')->nullable();
            $table->string('calculator_key')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_products');
    }
};
