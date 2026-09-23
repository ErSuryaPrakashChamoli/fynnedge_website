<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A NULL robots value means "not set here": the layout and the sitemap
     * already read it as "index, follow" on a record, and on a Page SEO
     * override it means "keep the page's own value". The old NOT NULL
     * DEFAULT 'index, follow' made every Page SEO row override robots even
     * when the admin never chose one. Existing rows are left untouched.
     */
    public function up(): void
    {
        Schema::table('seo_metas', function (Blueprint $table) {
            $table->string('robots')->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('seo_metas')->whereNull('robots')->update(['robots' => 'index, follow']);

        Schema::table('seo_metas', function (Blueprint $table) {
            $table->string('robots')->nullable(false)->default('index, follow')->change();
        });
    }
};
