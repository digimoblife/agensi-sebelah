<?php

namespace App\Models;

use App\Enums\JobRunStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property JobRunStatus $status
 * @property ?array<string, mixed> $options
 * @property ?CarbonImmutable $started_at
 * @property ?CarbonImmutable $finished_at
 */
class JobRun extends Model
{
    protected $fillable = ['idempotency_key', 'job', 'options', 'workspace_id', 'client_id', 'status', 'attempts', 'error_code', 'result_ref', 'requested_by', 'started_at', 'finished_at'];

    protected function casts(): array
    {
        return ['status' => JobRunStatus::class, 'options' => 'array', 'started_at' => 'immutable_datetime', 'finished_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
