<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // F9.2: the only source of facts for every role. Only approved, unexpired items reach the AI.
        Schema::create('knowledge_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('offering_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 20);
            $table->string('title', 160);
            $table->text('body');
            $table->string('source', 200)->nullable();
            $table->date('valid_until')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['client_id', 'approved_at']);
        });

        DB::statement("ALTER TABLE knowledge_items ADD CONSTRAINT knowledge_items_kind_check CHECK (kind IN ('fact', 'faq', 'policy', 'price', 'schedule', 'testimonial', 'do_dont'))");

        // F9.8: goals and primary KPI; reports and audit priorities refer to these.
        Schema::create('kpi_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('metric_key', 60);
            $table->string('label', 120);
            $table->decimal('target_value', 14, 2);
            $table->string('period', 10);
            $table->unsignedSmallInteger('priority')->default(1);
            $table->timestampsTz();

            $table->unique(['client_id', 'metric_key', 'period']);
        });

        DB::statement("ALTER TABLE kpi_goals ADD CONSTRAINT kpi_goals_period_check CHECK (period IN ('week', 'month', 'quarter'))");

        // F2.1 MVP: research material collected without an LLM (T0): admin notes and public sources.
        Schema::create('research_inputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('ref', 500);
            $table->text('note');
            $table->unsignedInteger('keyword_volume')->nullable();
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE research_inputs ADD CONSTRAINT research_inputs_kind_check CHECK (kind IN ('website', 'ad_library', 'google_trends', 'serp', 'social_public', 'manual_note'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('research_inputs');
        Schema::dropIfExists('kpi_goals');
        Schema::dropIfExists('knowledge_items');
    }
};
