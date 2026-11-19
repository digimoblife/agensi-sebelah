<?php

namespace App\Services\Llm;

use App\Models\ModelCatalogEntry;

/** docs/LLM-GATEWAY.md §Perhitungan biaya. Missing cache prices fall back to the full input price. */
final class CostCalculator
{
    public function cost(ModelCatalogEntry $model, int $input, int $cachedInput, int $cacheWrite, int $output): float
    {
        $in = (float) $model->price_input_per_mtok;

        return round((
            $input * $in
            + $cachedInput * (float) ($model->price_cached_input_per_mtok ?? $in)
            + $cacheWrite * (float) ($model->price_cache_write_per_mtok ?? $in)
            + $output * (float) $model->price_output_per_mtok
        ) / 1_000_000, 6);
    }

    public function estimate(ModelCatalogEntry $model, int $inputTokens, int $outputTokens): float
    {
        return $this->cost($model, $inputTokens, 0, 0, $outputTokens);
    }
}
