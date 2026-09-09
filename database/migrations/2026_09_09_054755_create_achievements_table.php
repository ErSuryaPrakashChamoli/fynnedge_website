<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('label');
            $table->string('value');
            $table->string('prefix')->nullable();
            $table->string('suffix')->nullable();
            $table->string('icon_path')->nullable();
            $table->string('icon_alt')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status')->default('draft');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('achievements');
    }
};
