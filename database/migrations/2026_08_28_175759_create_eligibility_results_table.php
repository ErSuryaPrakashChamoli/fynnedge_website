<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eligibility_results', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('journey_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lender_product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('eligibility_rule_set_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status');
            $table->decimal('foir', 6, 2)->nullable();
            $table->timestamp('evaluated_at');
            $table->timestamps();

            $table->unique(['journey_session_id', 'lender_product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eligibility_results');
    }
};
