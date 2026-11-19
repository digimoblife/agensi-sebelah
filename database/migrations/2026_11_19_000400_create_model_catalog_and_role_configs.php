<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_catalog', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 30);
            $table->string('model_id', 120);
            $table->string('label', 120);
            $table->jsonb('capabilities'); // text, vision, image, video, json_schema, prompt_cache, server_fallback
            // Prices in USD, entered and maintained by the admin. A model without text prices cannot be routed to.
            $table->decimal('price_input_per_mtok', 12, 4)->nullable();
            $table->decimal('price_cached_input_per_mtok', 12, 4)->nullable();
            $table->decimal('price_cache_write_per_mtok', 12, 4)->nullable();
            $table->decimal('price_output_per_mtok', 12, 4)->nullable();
            $table->decimal('price_per_image', 12, 4)->nullable();
            $table->decimal('price_per_video_second', 12, 4)->nullable();
            $table->unsignedInteger('max_output_tokens')->default(16000);
            $table->boolean('active')->default(true);
            $table->timestampsTz();

            $table->unique(['provider', 'model_id']);
        });

        DB::statement("ALTER TABLE model_catalog ADD CONSTRAINT model_catalog_provider_check CHECK (provider IN ('anthropic', 'openai', 'gemini', 'deepseek'))");

        Schema::create('role_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('role_key', 60);
            $table->foreignId('primary_model_id')->nullable()->constrained('model_catalog')->nullOnDelete();
            $table->foreignId('fallback_model_id')->nullable()->constrained('model_catalog')->nullOnDelete();
            $table->decimal('max_cost_per_call_usd', 10, 4)->nullable();
            $table->timestampsTz();

            $table->unique(['workspace_id', 'role_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_configs');
        Schema::dropIfExists('model_catalog');
    }
};
