<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('customer_name');
            $table->string('role_location')->nullable();
            $table->string('loan_category')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('quote');
            $table->string('avatar_path')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('testimonials');
    }
};
