<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// F9.7: Indonesian marketing moments (global, editable by the admin).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moment_calendar', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60);
            $table->string('name', 120);
            $table->string('kind', 20);
            $table->date('date_start');
            $table->date('date_end');
            $table->boolean('approximate')->default(false); // e.g. Islamic holidays before the government decree
            $table->string('notes', 300)->nullable();
            $table->timestampsTz();

            $table->unique(['key', 'date_start']);
            $table->index(['date_start', 'date_end']);
        });

        DB::statement("ALTER TABLE moment_calendar ADD CONSTRAINT moment_calendar_kind_check CHECK (kind IN ('religious', 'shopping', 'national', 'payday', 'school', 'season'))");
        DB::statement('ALTER TABLE moment_calendar ADD CONSTRAINT moment_calendar_range_check CHECK (date_end >= date_start)');
    }

    public function down(): void
    {
        Schema::dropIfExists('moment_calendar');
    }
};
