<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eligibility_rule_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eligibility_rule_id')->constrained()->cascadeOnDelete();
            $table->string('attribute');
            $table->string('operator');
            $table->json('value')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eligibility_rule_conditions');
    }
};
