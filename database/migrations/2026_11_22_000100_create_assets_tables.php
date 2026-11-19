<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// F9.3 asset library (PRD §Sumber aset). Files live on the private `assets` disk.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('offering_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 20);
            $table->string('source', 20);
            $table->string('title', 160);
            $table->string('path', 300);
            $table->string('mime', 80);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('bytes');
            $table->jsonb('tags')->nullable();
            $table->boolean('has_people')->default(false);
            $table->boolean('has_children')->default(false);
            $table->string('consent_status', 20);
            $table->string('license', 200)->nullable();
            $table->boolean('ai_generated')->default(false);
            $table->timestampsTz();

            $table->index(['client_id', 'kind']);
        });

        DB::statement("ALTER TABLE assets ADD CONSTRAINT assets_kind_check CHECK (kind IN ('logo', 'photo', 'video', 'screenshot', 'illustration', 'render', 'ai_image', 'ai_video'))");
        DB::statement("ALTER TABLE assets ADD CONSTRAINT assets_source_check CHECK (source IN ('client', 'ugc', 'auto', 'ai', 'stock', 'system'))");
        DB::statement("ALTER TABLE assets ADD CONSTRAINT assets_consent_check CHECK (consent_status IN ('not_needed', 'granted', 'pending', 'refused'))");

        Schema::create('asset_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_item_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20); // source (photo used) or output (rendered file)
            $table->timestampTz('used_at');

            $table->unique(['asset_id', 'content_item_id', 'role']);
        });

        Schema::table('content_items', function (Blueprint $table) {
            // Current visual preview/final: rendered asset ulids + hash of the draft they were rendered from.
            $table->jsonb('render')->nullable()->after('guardian');
            $table->timestampTz('sent_for_approval_at')->nullable()->after('render');
        });
    }

    public function down(): void
    {
        Schema::table('content_items', fn (Blueprint $table) => $table->dropColumn(['render', 'sent_for_approval_at']));
        Schema::dropIfExists('asset_usages');
        Schema::dropIfExists('assets');
    }
};
