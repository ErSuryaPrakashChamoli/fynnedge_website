<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_type_id')->constrained()->cascadeOnDelete();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_filename');
            $table->string('mime_type');
            $table->unsignedInteger('size');
            $table->string('status')->default('uploaded');
            $table->string('rejection_reason')->nullable();
            $table->timestamp('uploaded_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['application_id', 'document_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_documents');
    }
};
