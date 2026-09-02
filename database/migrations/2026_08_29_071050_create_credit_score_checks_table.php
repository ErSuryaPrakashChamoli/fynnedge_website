<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_score_checks', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('bureau');
            $table->string('mobile_number');
            $table->timestamp('mobile_verified_at');
            $table->string('full_name');
            $table->date('date_of_birth');
            $table->string('pan_number');
            $table->string('provider');
            $table->string('status')->default('not_requested');
            $table->unsignedSmallInteger('score')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamp('consent_given_at');
            $table->string('ip_address')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_score_checks');
    }
};
