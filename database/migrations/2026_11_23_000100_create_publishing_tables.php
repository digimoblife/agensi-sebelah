<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// F1.2 account connections, F4.2/F4.3 publishing, F9.5 ready-to-upload packages (ADR 0003 catch-up).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oauth_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 30);
            $table->string('account_ref', 120)->nullable();   // IG user id, GA4 property id
            $table->string('account_name', 160)->nullable();
            $table->text('access_token')->nullable();         // encrypted
            $table->text('refresh_token')->nullable();        // encrypted
            $table->timestampTz('expires_at')->nullable();
            $table->jsonb('scopes')->nullable();
            $table->jsonb('meta')->nullable();                // e.g. page id, candidate accounts
            $table->string('status', 20);
            $table->string('last_error_code', 80)->nullable();
            $table->timestampsTz();

            $table->unique(['client_id', 'platform']);
        });

        DB::statement("ALTER TABLE oauth_connections ADD CONSTRAINT oauth_connections_platform_check CHECK (platform IN ('instagram', 'ga4'))");
        DB::statement("ALTER TABLE oauth_connections ADD CONSTRAINT oauth_connections_status_check CHECK (status IN ('pending', 'needs_selection', 'active', 'error', 'revoked'))");

        Schema::create('publish_jobs', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_item_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 30);
            $table->timestampTz('scheduled_at');
            $table->string('mode', 20);
            $table->string('status', 20);
            $table->jsonb('qa')->nullable();
            $table->string('external_post_id', 120)->nullable();
            $table->string('permalink', 300)->nullable();
            $table->foreignId('package_asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->string('reminder_token', 32)->nullable()->unique();
            $table->timestampTz('reminded_at')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->string('error_code', 80)->nullable();
            $table->timestampsTz();

            $table->index(['status', 'scheduled_at']);
        });

        // At most one active job per content item (prevents double publishing).
        DB::statement("CREATE UNIQUE INDEX publish_jobs_active_unique ON publish_jobs (content_item_id) WHERE status IN ('scheduled', 'publishing')");
        DB::statement("ALTER TABLE publish_jobs ADD CONSTRAINT publish_jobs_mode_check CHECK (mode IN ('api', 'manual_package'))");
        DB::statement("ALTER TABLE publish_jobs ADD CONSTRAINT publish_jobs_status_check CHECK (status IN ('scheduled', 'publishing', 'published', 'failed', 'missed', 'packaged', 'manually_uploaded', 'cancelled'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('publish_jobs');
        Schema::dropIfExists('oauth_connections');
    }
};
