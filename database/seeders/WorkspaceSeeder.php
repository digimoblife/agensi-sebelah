<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class WorkspaceSeeder extends Seeder
{
    public function run(): void
    {
        $workspace = Workspace::query()->orderBy('id')->first() ?? Workspace::query()->create(['name' => 'Agensi Sebelah']);

        // v1 is single-workspace: attach any admin created before seeding.
        User::query()->whereNull('workspace_id')->update(['workspace_id' => $workspace->id]);
    }
}
