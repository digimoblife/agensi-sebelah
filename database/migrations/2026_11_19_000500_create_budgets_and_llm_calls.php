<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->date('month'); // first day of the month (client calendar)
            $table->decimal('llm_limit_usd', 10, 2);
            $table->decimal('media_limit_usd', 10, 2);
            $table->decimal('llm_spent_usd', 12, 6)->default(0);
            $table->decimal('media_spent_usd', 12, 6)->default(0);
            $table->timestampTz('alert_80_sent_at')->nullable();
            $table->timestampTz('alert_100_sent_at')->nullable();
            $table->timestampsTz();

            $table->unique(['client_id', 'month']);
        });

        Schema::create('llm_calls', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('role_key', 60);
            $table->string('task_ref', 120);
            $table->string('prompt_key', 60);
            $table->unsignedInteger('prompt_version');
            $table->string('provider', 30)->nullable();
            $table->foreignId('model_catalog_id')->nullable()->constrained('model_catalog')->nullOnDelete();
            $table->string('model_id', 120)->nullable();
            $table->unsignedSmallInteger('attempt');
            $table->foreignId('fallback_from_call_id')->nullable()->constrained('llm_calls')->nullOnDelete();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('cached_input_tokens')->default(0);
            $table->unsignedInteger('cache_write_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->decimal('cost_usd', 12, 6)->default(0);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->string('status', 30);
            $table->string('error_code', 60)->nullable();
            $table->char('request_hash', 64);
            $table->timestampTz('created_at');

            $table->index(['client_id', 'created_at']);
            $table->index(['role_key', 'prompt_version']);
            $table->index('request_hash');
        });

        DB::statement("ALTER TABLE llm_calls ADD CONSTRAINT llm_calls_status_check CHECK (status IN ('ok', 'schema_invalid', 'provider_error', 'rate_limited', 'refused', 'budget_blocked', 'cache_hit'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('llm_calls');
        Schema::dropIfExists('budgets');
    }
};
