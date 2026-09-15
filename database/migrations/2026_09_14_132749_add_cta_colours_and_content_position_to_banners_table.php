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
        Schema::table('banners', function (Blueprint $table) {
            $table->string('cta_bg_color', 7)->nullable()->after('cta_url');
            $table->string('cta_hover_color', 7)->nullable()->after('cta_bg_color');
            $table->string('cta_text_color', 7)->nullable()->after('cta_hover_color');
            $table->string('content_vertical_align')->default('bottom')->after('cta_text_color');
            $table->string('content_horizontal_align')->default('left')->after('content_vertical_align');
            $table->unsignedTinyInteger('content_padding_left')->nullable()->after('content_horizontal_align');
            $table->unsignedTinyInteger('content_padding_right')->nullable()->after('content_padding_left');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->dropColumn([
                'cta_bg_color',
                'cta_hover_color',
                'cta_text_color',
                'content_vertical_align',
                'content_horizontal_align',
                'content_padding_left',
                'content_padding_right',
            ]);
        });
    }
};
