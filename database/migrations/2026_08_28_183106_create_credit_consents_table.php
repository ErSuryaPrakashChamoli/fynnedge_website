<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_consents', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('journey_session_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('purpose');
            $table->string('terms_version');
            $table->string('ip_address')->nullable();
            $table->string('status')->default('given');
            $table->timestamp('consented_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_consents');
    }
};
