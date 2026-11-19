<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// docs/ARTIFACTS.md
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artifacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('scope_ref', 80)->nullable(); // e.g. "2026-12" for a monthly content plan
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->timestampsTz();

            $table->index(['client_id', 'type']);
        });

        // NULL scope_ref must also be unique per (client, type), which a plain unique index does not enforce.
        DB::statement("CREATE UNIQUE INDEX artifacts_client_type_scope_unique ON artifacts (client_id, type, COALESCE(scope_ref, ''))");
        DB::statement("ALTER TABLE artifacts ADD CONSTRAINT artifacts_type_check CHECK (type IN ('brand_brief', 'visual_rules', 'research_pack', 'content_plan', 'kpi_snapshot'))");

        Schema::create('artifact_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artifact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->unsignedSmallInteger('schema_version');
            $table->string('status', 20);
            $table->jsonb('payload');
            $table->unsignedInteger('token_estimate');
            $table->foreignId('produced_by_llm_call_id')->nullable()->constrained('llm_calls')->nullOnDelete();
            $table->string('change_note', 300)->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampsTz();

            $table->unique(['artifact_id', 'version']);
        });

        DB::statement("ALTER TABLE artifact_versions ADD CONSTRAINT artifact_versions_status_check CHECK (status IN ('draft', 'in_review', 'approved', 'superseded', 'rejected'))");
        DB::statement("CREATE INDEX artifact_versions_approved_idx ON artifact_versions (artifact_id, version) WHERE status = 'approved'");

        Schema::table('artifacts', function (Blueprint $table) {
            $table->foreign('current_version_id')->references('id')->on('artifact_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('artifacts', fn (Blueprint $table) => $table->dropForeign(['current_version_id']));
        Schema::dropIfExists('artifact_versions');
        Schema::dropIfExists('artifacts');
    }
};
