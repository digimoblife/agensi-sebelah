<?php

namespace Database\Factories;

use App\Enums\ClientStatus;
use App\Models\Client;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Client> */
class ClientFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Klien '.fake()->unique()->word();

        return [
            'workspace_id' => Workspace::query()->value('id') ?? Workspace::query()->create(['name' => 'Test'])->id,
            'name' => $name,
            'slug' => Str::slug($name),
            'status' => ClientStatus::Active,
            'timezone' => 'Asia/Jakarta',
        ];
    }
}
