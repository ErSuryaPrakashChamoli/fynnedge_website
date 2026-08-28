<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journey_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journey_session_id')->constrained()->cascadeOnDelete();
            $table->string('field_key');
            $table->json('value')->nullable();
            $table->timestamps();

            $table->unique(['journey_session_id', 'field_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journey_responses');
    }
};
