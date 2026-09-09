<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schema_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('name')->unique();
            $table->string('schema_type');
            $table->text('notes')->nullable();
            $table->json('body');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'schema_type'], 'schema_templates_active_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schema_templates');
    }
};
