<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journey_steps', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('journey_definition_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->json('condition_rules')->nullable();
            $table->timestamps();

            $table->unique(['journey_definition_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journey_steps');
    }
};
