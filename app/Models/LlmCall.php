<?php

namespace App\Models;

use App\Enums\LlmCallStatus;
use App\Enums\LlmProvider;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * F8.4: one row per attempt. The prompt itself is never stored, only its hash.
 *
 * @property ?LlmProvider $provider
 * @property LlmCallStatus $status
 */
class LlmCall extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'workspace_id', 'client_id', 'role_key', 'task_ref', 'prompt_key', 'prompt_version', 'provider',
        'model_catalog_id', 'model_id', 'attempt', 'fallback_from_call_id', 'input_tokens', 'cached_input_tokens',
        'cache_write_tokens', 'output_tokens', 'cost_usd', 'latency_ms', 'status', 'error_code', 'request_hash',
    ];

    protected function casts(): array
    {
        return [
            'provider' => LlmProvider::class,
            'status' => LlmCallStatus::class,
        ];
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
