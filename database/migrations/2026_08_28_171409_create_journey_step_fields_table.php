<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journey_step_fields', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('journey_step_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->string('type')->default('text');
            $table->json('options')->nullable();
            $table->json('validation_rules')->nullable();
            $table->string('help_text')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->json('conditional_on')->nullable();
            $table->timestamps();

            $table->unique(['journey_step_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journey_step_fields');
    }
};
