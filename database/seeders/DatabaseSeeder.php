<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeds only what every installation needs. No client data and no admin account:
     * create the admin with `php artisan make:filament-user`, and add clients from the dashboard.
     */
    public function run(): void
    {
        $this->call([
            WorkspaceSeeder::class,
            ModelCatalogSeeder::class,
        ]);
    }
}
