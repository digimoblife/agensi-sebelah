<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Manual AI image flow: the admin generates the image in a chat app from the prompt the dashboard composes,
// uploads it, and it becomes this item's background image for the template render.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_items', function (Blueprint $table) {
            $table->foreignId('visual_asset_id')->nullable()->after('render')->constrained('assets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('content_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('visual_asset_id'));
    }
};
