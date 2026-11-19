<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// docs/specs/credentials-settings.md
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 30);
            $table->string('key', 50);
            $table->text('value'); // encrypted with APP_KEY
            $table->string('last4', 4)->nullable(); // null for non-secret fields
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['workspace_id', 'provider', 'key']);
        });

        // Connection status is per provider, not per field.
        Schema::create('provider_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 30);
            $table->string('status', 20);
            $table->string('message', 200)->nullable();
            $table->string('last_error_code', 60)->nullable();
            $table->timestampTz('last_tested_at')->nullable();
            $table->timestampsTz();

            $table->unique(['workspace_id', 'provider']);
        });

        DB::statement("ALTER TABLE provider_statuses ADD CONSTRAINT provider_statuses_status_check CHECK (status IN ('untested', 'ok', 'failed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_statuses');
        Schema::dropIfExists('provider_credentials');
    }
};
