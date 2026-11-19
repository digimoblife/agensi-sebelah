<?php

use App\Models\ModelCatalogEntry;
use App\Services\Llm\CostCalculator;

it('prices uncached input, cached reads, cache writes and output separately', function () {
    $model = new ModelCatalogEntry([
        'price_input_per_mtok' => 4.00, 'price_cached_input_per_mtok' => 0.20,
        'price_cache_write_per_mtok' => 5.00, 'price_output_per_mtok' => 20.00,
    ]);

    // 1M uncached = $4, 2M cached = $0.40, 0.1M write = $0.50, 0.5M out = $10
    expect((new CostCalculator)->cost($model, 1_000_000, 2_000_000, 100_000, 500_000))->toBe(14.9);
});

it('falls back to the input price when cache prices are missing', function () {
    $model = new ModelCatalogEntry(['price_input_per_mtok' => 1.00, 'price_output_per_mtok' => 2.00]);

    expect((new CostCalculator)->cost($model, 0, 1_000_000, 1_000_000, 0))->toBe(2.0);
});
