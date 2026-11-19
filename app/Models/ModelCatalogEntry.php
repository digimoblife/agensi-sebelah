<?php

namespace App\Models;

use App\Enums\LlmProvider;
use Database\Factories\ModelCatalogEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property LlmProvider $provider
 * @property array<int, string> $capabilities
 * @property ?string $price_input_per_mtok
 * @property ?string $price_cached_input_per_mtok
 * @property ?string $price_cache_write_per_mtok
 * @property ?string $price_output_per_mtok
 */
class ModelCatalogEntry extends Model
{
    /** @use HasFactory<ModelCatalogEntryFactory> */
    use HasFactory;

    protected $table = 'model_catalog';

    protected $fillable = [
        'provider', 'model_id', 'label', 'capabilities', 'price_input_per_mtok', 'price_cached_input_per_mtok',
        'price_cache_write_per_mtok', 'price_output_per_mtok', 'price_per_image', 'price_per_video_second',
        'max_output_tokens', 'active',
    ];

    protected function casts(): array
    {
        return [
            'provider' => LlmProvider::class,
            'capabilities' => 'array',
            'active' => 'boolean',
            'max_output_tokens' => 'integer',
        ];
    }

    public function hasCapability(string $capability): bool
    {
        return in_array($capability, $this->capabilities, true);
    }

    /** A text model can be routed to only when the admin has entered its token prices. */
    public function isRoutable(): bool
    {
        return $this->active && $this->hasCapability('text')
            && $this->price_input_per_mtok !== null && $this->price_output_per_mtok !== null;
    }
}
