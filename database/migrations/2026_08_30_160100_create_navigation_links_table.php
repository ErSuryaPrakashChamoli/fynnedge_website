<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin-managed links that are appended ALONGSIDE the site's existing
     * hardcoded navigation — never a replacement for it. See .ai/rules for
     * why the header mega-menus and footer's Company/Legal columns stay
     * hardcoded (business/legal/route-coupled content, too risky to hand to
     * a generic link editor).
     */
    public function up(): void
    {
        Schema::create('navigation_links', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('label');
            $table->string('url')->nullable();
            $table->string('route_name')->nullable();
            $table->boolean('is_external')->default(false);
            $table->string('location')->default('footer');
            $table->foreignId('parent_id')->nullable()->constrained('navigation_links')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('navigation_links');
    }
};
