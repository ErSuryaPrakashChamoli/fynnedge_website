<?php

use App\Models\Redirect;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a redirects row say "Gone" (410) instead of pointing somewhere: an old
 * URL whose content was removed for good and has no equivalent page. A Gone
 * row has no destination, so the column becomes nullable. `notes` records why
 * a row exists (which report, which old page), so the next admin can judge
 * whether it is still needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('redirects', function (Blueprint $table) {
            $table->string('destination')->nullable()->comment('Absolute URL, or a site-relative path starting with /. Null on a 410 Gone row')->change();
            $table->text('notes')->nullable()->after('is_active');
        });
    }

    /**
     * A Gone row cannot survive a NOT NULL destination, so rolling back drops
     * them first. They are recreated by re-running the migrations that ship
     * them, or by hand in Admin → Redirects.
     */
    public function down(): void
    {
        DB::table('redirects')->whereNull('destination')->delete();

        Schema::table('redirects', function (Blueprint $table) {
            $table->dropColumn('notes');
            $table->string('destination')->nullable(false)->comment('Absolute URL, or a site-relative path starting with /')->change();
        });

        Redirect::forgetCache();
    }
};
