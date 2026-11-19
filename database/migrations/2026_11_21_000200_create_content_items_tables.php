<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// docs/WORKFLOWS.md: one row per piece of content; revisions change only that row (PRD principle 9).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_items', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_plan_version_id')->nullable()->constrained('artifact_versions')->nullOnDelete();
            $table->string('month', 7);
            $table->string('plan_item_key', 40);
            $table->foreignId('offering_id')->nullable()->constrained()->nullOnDelete();
            $table->string('platform', 30);
            $table->string('format', 30);
            $table->string('pillar', 40);
            $table->string('moment_key', 60)->nullable();
            $table->date('publish_date');
            $table->string('publish_time', 5)->nullable();
            $table->string('angle', 200);
            $table->string('cta', 80);
            $table->string('asset_hint', 160)->nullable();
            $table->string('status', 30);
            $table->jsonb('draft')->nullable();      // content_item.v1 payload
            $table->jsonb('guardian')->nullable();   // last Brand Guardian result (rules + review)
            $table->text('revision_comment')->nullable();
            $table->unsignedSmallInteger('revision_count')->default(0);
            $table->unsignedSmallInteger('auto_revision_count')->default(0);
            $table->boolean('major_revision')->default(false); // K5: rejected, regenerated > 1, or flagged major
            $table->foreignId('last_llm_call_id')->nullable()->constrained('llm_calls')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['client_id', 'month', 'plan_item_key']);
            $table->index(['client_id', 'status']);
            $table->index(['client_id', 'publish_date']);
        });

        DB::statement("ALTER TABLE content_items ADD CONSTRAINT content_items_status_check CHECK (status IN (
            'planned', 'drafting', 'drafted', 'guardian_failed', 'awaiting_approval', 'revision_requested', 'approved',
            'rejected', 'cancelled', 'rendered', 'scheduled', 'published', 'publish_failed', 'packaged', 'manually_uploaded'))");

        Schema::create('workflow_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type', 40);
            $table->unsignedBigInteger('subject_id');
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->string('actor', 60);
            $table->string('reason', 300)->nullable();
            $table->timestampTz('created_at');

            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_item_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 20);
            $table->string('decision', 20);
            $table->text('comment')->nullable();
            $table->jsonb('reason_tags')->nullable();
            $table->boolean('is_major')->default(false);
            $table->string('decided_by', 60);
            $table->timestampTz('decided_at');
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE approvals ADD CONSTRAINT approvals_channel_check CHECK (channel IN ('dashboard', 'telegram'))");
        DB::statement("ALTER TABLE approvals ADD CONSTRAINT approvals_decision_check CHECK (decision IN ('approved', 'revise', 'rejected'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('approvals');
        Schema::dropIfExists('workflow_events');
        Schema::dropIfExists('content_items');
    }
};
