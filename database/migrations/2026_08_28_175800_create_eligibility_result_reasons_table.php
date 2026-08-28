<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eligibility_result_reasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eligibility_result_id')->constrained()->cascadeOnDelete();
            $table->foreignId('eligibility_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label');
            $table->string('priority');
            $table->boolean('passed');
            $table->string('customer_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eligibility_result_reasons');
    }
};
