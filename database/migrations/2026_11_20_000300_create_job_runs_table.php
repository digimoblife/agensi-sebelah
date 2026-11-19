<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// ADR 0003 + F9.6: Postgres, not Redis, is the record of every important job.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_runs', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key', 160)->unique();
            $table->string('job', 80);
            $table->foreignId('workspace_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('error_code', 120)->nullable();
            $table->string('result_ref', 120)->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestampsTz();

            $table->index(['client_id', 'created_at']);
            $table->index('status');
        });

        DB::statement("ALTER TABLE job_runs ADD CONSTRAINT job_runs_status_check CHECK (status IN ('queued', 'running', 'succeeded', 'failed', 'needs_manual'))");

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->jsonb('data');
            $table->timestampTz('read_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('job_runs');
    }
};
