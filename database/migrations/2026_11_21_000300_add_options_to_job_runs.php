<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Job options (e.g. content plan month and platforms) so the job console can re-run a job exactly.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_runs', fn (Blueprint $table) => $table->jsonb('options')->nullable()->after('job'));
    }

    public function down(): void
    {
        Schema::table('job_runs', fn (Blueprint $table) => $table->dropColumn('options'));
    }
};
