<?php

namespace App\Models;

use App\Enums\ArtifactStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<string, mixed> $payload
 * @property ArtifactStatus $status
 */
class ArtifactVersion extends Model
{
    protected $fillable = [
        'artifact_id', 'client_id', 'version', 'schema_version', 'status', 'payload', 'token_estimate',
        'produced_by_llm_call_id', 'change_note', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ArtifactStatus::class,
            'payload' => 'array',
            'approved_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Artifact, $this> */
    public function artifact(): BelongsTo
    {
        return $this->belongsTo(Artifact::class);
    }
}
