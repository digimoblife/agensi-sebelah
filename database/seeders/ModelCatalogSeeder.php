<?php

namespace Database\Seeders;

use App\Models\ModelCatalogEntry;
use Illuminate\Database\Seeder;

/**
 * Starting catalog. Prices are USD per 1M tokens as published by Anthropic (checked Nov 2026) and are
 * maintained by the admin from then on (PRD: "Harga diisi dan diperbarui admin"). OpenAI, Gemini and
 * DeepSeek models are added by the admin in the dashboard with their current prices; a model without
 * prices is never routed to.
 */
class ModelCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $models = [
            [
                'model_id' => 'claude-opus-5-5', 'label' => 'Claude Opus 5.5',
                'capabilities' => ['text', 'vision', 'json_schema', 'prompt_cache', 'server_fallback'],
                'price_input_per_mtok' => 4.00, 'price_cached_input_per_mtok' => 0.20, 'price_cache_write_per_mtok' => 5.00,
                'price_output_per_mtok' => 20.00,
            ],
            [
                'model_id' => 'claude-sonnet-5-5', 'label' => 'Claude Sonnet 5.5',
                'capabilities' => ['text', 'vision', 'json_schema', 'prompt_cache', 'server_fallback'],
                'price_input_per_mtok' => 2.00, 'price_cached_input_per_mtok' => 0.10, 'price_cache_write_per_mtok' => 2.50,
                'price_output_per_mtok' => 10.00,
            ],
            [
                'model_id' => 'claude-haiku-5-5', 'label' => 'Claude Haiku 5.5',
                'capabilities' => ['text', 'vision', 'json_schema', 'prompt_cache'],
                // Cache prices not seeded: the calculator falls back to the full input price until the admin sets them.
                'price_input_per_mtok' => 0.10, 'price_output_per_mtok' => 0.50,
            ],
        ];

        foreach ($models as $model) {
            ModelCatalogEntry::query()->firstOrCreate(
                ['provider' => 'anthropic', 'model_id' => $model['model_id']],
                $model + ['max_output_tokens' => 16000, 'active' => true],
            );
        }
    }
}
