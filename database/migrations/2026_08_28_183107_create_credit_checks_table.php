<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_checks', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('credit_consent_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('reference')->nullable();
            $table->string('status')->default('not_requested');
            $table->unsignedSmallInteger('score')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_checks');
    }
};
