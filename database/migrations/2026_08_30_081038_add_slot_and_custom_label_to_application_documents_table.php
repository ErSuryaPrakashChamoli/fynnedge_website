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
        Schema::table('application_documents', function (Blueprint $table) {
            $table->unsignedInteger('slot')->default(0)->after('document_type_id');
            $table->string('custom_label')->nullable()->after('slot');
        });

        Schema::table('application_documents', function (Blueprint $table) {
            // Add the replacement unique index before dropping the old one — MySQL
            // uses the old (application_id, document_type_id) index to back the
            // application_id foreign key, and refuses to drop it while it's the
            // only index covering that key.
            $table->unique(['application_id', 'document_type_id', 'slot'], 'application_document_slot_unique');
        });

        Schema::table('application_documents', function (Blueprint $table) {
            $table->dropUnique(['application_id', 'document_type_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('application_documents', function (Blueprint $table) {
            $table->unique(['application_id', 'document_type_id']);
        });

        Schema::table('application_documents', function (Blueprint $table) {
            $table->dropUnique('application_document_slot_unique');
            $table->dropColumn(['slot', 'custom_label']);
        });
    }
};
