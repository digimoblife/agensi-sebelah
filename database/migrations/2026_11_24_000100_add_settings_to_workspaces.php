<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Workspace-level settings, e.g. the Paperclip cost baseline for the MVP gate comparison.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', fn (Blueprint $table) => $table->jsonb('settings')->nullable());
    }

    public function down(): void
    {
        Schema::table('workspaces', fn (Blueprint $table) => $table->dropColumn('settings'));
    }
};
