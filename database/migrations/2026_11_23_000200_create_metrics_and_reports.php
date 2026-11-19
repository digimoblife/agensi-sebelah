<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// F7 (MVP: Instagram + GA4) warehouse and F8.1 reports.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metrics_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('source', 20);
            $table->string('entity_type', 20);   // account, post, event
            $table->string('entity_ref', 120);   // account id, IG media id, GA4 event name
            $table->date('date');
            $table->string('metric_key', 60);
            $table->decimal('value', 18, 4);
            $table->foreignId('content_item_id')->nullable()->constrained()->nullOnDelete();
            $table->timestampTz('pulled_at');

            $table->unique(['client_id', 'source', 'entity_type', 'entity_ref', 'date', 'metric_key'], 'metrics_daily_unique');
            $table->index(['client_id', 'date']);
        });

        Schema::create('metric_pulls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('source', 20);
            $table->date('last_complete_date')->nullable();
            $table->string('last_error_code', 80)->nullable();
            $table->timestampTz('last_run_at')->nullable();
            $table->timestampsTz();

            $table->unique(['client_id', 'source']);
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('period_type', 10);
            $table->date('period_start');
            $table->date('period_end');
            $table->foreignId('kpi_snapshot_version_id')->nullable()->constrained('artifact_versions')->nullOnDelete();
            $table->jsonb('narrative')->nullable();
            $table->foreignId('pdf_asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->foreignId('llm_call_id')->nullable()->constrained('llm_calls')->nullOnDelete();
            $table->string('status', 20);
            $table->timestampTz('sent_at')->nullable();
            $table->timestampsTz();

            $table->unique(['client_id', 'period_type', 'period_start']);
        });

        DB::statement("ALTER TABLE reports ADD CONSTRAINT reports_status_check CHECK (status IN ('draft', 'ready', 'sent', 'failed'))");
        DB::statement('ALTER TABLE assets DROP CONSTRAINT assets_kind_check');
        DB::statement("ALTER TABLE assets ADD CONSTRAINT assets_kind_check CHECK (kind IN ('logo', 'photo', 'video', 'screenshot', 'illustration', 'render', 'ai_image', 'ai_video', 'package', 'report'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE assets DROP CONSTRAINT assets_kind_check');
        DB::statement("ALTER TABLE assets ADD CONSTRAINT assets_kind_check CHECK (kind IN ('logo', 'photo', 'video', 'screenshot', 'illustration', 'render', 'ai_image', 'ai_video'))");
        Schema::dropIfExists('reports');
        Schema::dropIfExists('metric_pulls');
        Schema::dropIfExists('metrics_daily');
    }
};
