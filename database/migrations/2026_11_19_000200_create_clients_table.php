<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->ulid('ulid')->unique();
            $table->string('name');
            $table->string('slug');
            $table->string('status', 20);
            $table->string('timezone', 64)->default('Asia/Jakarta');
            $table->timestampsTz();

            $table->unique(['workspace_id', 'slug']);
        });

        DB::statement("ALTER TABLE clients ADD CONSTRAINT clients_status_check CHECK (status IN ('onboarding', 'active', 'paused', 'archived'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
