<?php

namespace Database\Factories;

use App\Models\ModelCatalogEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ModelCatalogEntry> */
class ModelCatalogEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'provider' => 'anthropic',
            'model_id' => 'test-model-'.fake()->unique()->numberBetween(1, 99999),
            'label' => 'Test model',
            'capabilities' => ['text', 'json_schema'],
            'price_input_per_mtok' => 1.00,
            'price_cached_input_per_mtok' => 0.10,
            'price_cache_write_per_mtok' => 1.25,
            'price_output_per_mtok' => 5.00,
            'max_output_tokens' => 16000,
            'active' => true,
        ];
    }
}
