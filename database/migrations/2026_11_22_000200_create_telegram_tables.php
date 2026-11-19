<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ADR 0004: client approval via Telegram (long polling).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->boolean('can_approve')->default(true);
            $table->bigInteger('telegram_chat_id')->nullable();
            $table->string('invite_code_hash', 64)->nullable()->unique();
            $table->timestampTz('invite_expires_at')->nullable();
            $table->timestampTz('linked_at')->nullable();
            $table->timestampsTz();

            $table->unique(['client_id', 'telegram_chat_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->bigInteger('telegram_chat_id')->nullable()->unique();
            $table->string('telegram_invite_hash', 64)->nullable()->unique();
            $table->timestampTz('telegram_invite_expires_at')->nullable();
        });

        // One per item sent to one contact. Buttons carry the random token, never database ids.
        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->string('token', 32)->unique();
            $table->foreignId('content_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_contact_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('chat_id');
            $table->bigInteger('message_id')->nullable();
            $table->timestampTz('expires_at');
            $table->timestampTz('awaiting_comment_at')->nullable(); // "Revisi" pressed; next text is the comment
            $table->string('decision', 20)->nullable();
            $table->timestampTz('decided_at')->nullable();
            $table->timestampsTz();

            $table->index(['chat_id', 'awaiting_comment_at']);
        });

        // Idempotent update processing: an update_id is handled once even if polling repeats it.
        Schema::create('telegram_updates', function (Blueprint $table) {
            $table->bigInteger('update_id')->primary();
            $table->string('kind', 30);
            $table->string('outcome', 40)->nullable();
            $table->timestampTz('processed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_updates');
        Schema::dropIfExists('approval_requests');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['telegram_chat_id', 'telegram_invite_hash', 'telegram_invite_expires_at']));
        Schema::dropIfExists('client_contacts');
    }
};
