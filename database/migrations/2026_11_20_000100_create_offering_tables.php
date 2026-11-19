<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// F9.1 offering type profiles + generic "Offering" entity (PRD §Jenis bisnis dan pustaka aset).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offering_types', function (Blueprint $table) {
            $table->string('key', 40)->primary();
            $table->string('label', 80);
            $table->string('primary_conversion', 60);
            $table->jsonb('conversion_events');
            $table->jsonb('default_kpis');
            $table->jsonb('content_pillars');
            $table->jsonb('ctas');
            $table->jsonb('required_assets');
            $table->jsonb('compliance_notes');
            $table->timestampsTz();
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->string('primary_offering_type', 40)->nullable()->after('status');
            $table->foreign('primary_offering_type')->references('key')->on('offering_types')->nullOnDelete();
            // F1.1 onboarding answers (business, audience, competitors, account access). Facts go to knowledge_items.
            $table->jsonb('onboarding')->nullable();
            $table->decimal('llm_budget_usd', 10, 2)->nullable();
            $table->decimal('media_budget_usd', 10, 2)->nullable();
        });

        Schema::create('offerings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('offering_type', 40);
            $table->foreign('offering_type')->references('key')->on('offering_types')->restrictOnDelete();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->decimal('price', 14, 2)->nullable();
            $table->string('currency', 3)->default('IDR');
            $table->jsonb('schedule')->nullable();
            $table->string('url', 500)->nullable();
            $table->string('status', 20);
            $table->timestampsTz();

            $table->index(['client_id', 'status']);
        });

        DB::statement("ALTER TABLE offerings ADD CONSTRAINT offerings_status_check CHECK (status IN ('active', 'upcoming', 'archived'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('offerings');
        Schema::table('clients', function (Blueprint $table) {
            $table->dropForeign(['primary_offering_type']);
            $table->dropColumn(['primary_offering_type', 'onboarding', 'llm_budget_usd', 'media_budget_usd']);
        });
        Schema::dropIfExists('offering_types');
    }
};
