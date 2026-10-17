<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Other systems (e.g. the LMS) that call the FynnEdge API with their own key —
 * Admin → Access Control → API Clients. Each client acts as its own service
 * account (user_id: a users row that cannot sign in to the admin), so the
 * roles, policies and audit trail staff already have apply unchanged.
 *
 * Only a SHA-256 hash of the key is stored (key_hash, unique — the lookup);
 * key_prefix is its first characters, kept so an admin can tell keys apart.
 * A client is never deleted, only revoked, so the requests it made keep their
 * "requested by".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_clients', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('name', 100);
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('key_prefix', 16);
            $table->char('key_hash', 64)->unique();
            $table->timestamp('key_generated_at')->nullable();
            $table->json('allowed_ips')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip', 45)->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_clients');
    }
};
