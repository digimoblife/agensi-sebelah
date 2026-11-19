<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CarbonImmutable $period_start
 * @property CarbonImmutable $period_end
 * @property ?array<string, mixed> $narrative
 */
class Report extends Model
{
    use HasUlids;

    protected $fillable = ['workspace_id', 'client_id', 'period_type', 'period_start', 'period_end', 'kpi_snapshot_version_id', 'narrative', 'pdf_asset_id', 'llm_call_id', 'status', 'sent_at'];

    protected function casts(): array
    {
        return ['period_start' => 'immutable_date', 'period_end' => 'immutable_date', 'narrative' => 'array', 'sent_at' => 'immutable_datetime'];
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

    /** @return BelongsTo<Asset, $this> */
    public function pdf(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'pdf_asset_id');
    }

    /** @return BelongsTo<ArtifactVersion, $this> */
    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(ArtifactVersion::class, 'kpi_snapshot_version_id');
    }
}
