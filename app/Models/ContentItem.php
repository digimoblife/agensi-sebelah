<?php

namespace App\Models;

use App\Enums\ContentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Status changes go through App\Services\Workflow\ContentWorkflow only (CLAUDE.md rule 6).
 *
 * @property ContentStatus $status
 * @property CarbonImmutable $publish_date
 * @property ?array<string, mixed> $draft
 * @property ?array<string, mixed> $guardian
 */
class ContentItem extends Model
{
    use HasUlids;

    protected $fillable = [
        'workspace_id', 'client_id', 'content_plan_version_id', 'month', 'plan_item_key', 'offering_id', 'platform',
        'format', 'pillar', 'moment_key', 'publish_date', 'publish_time', 'angle', 'cta', 'asset_hint', 'status', 'draft',
        'guardian', 'revision_comment', 'revision_count', 'auto_revision_count', 'major_revision', 'last_llm_call_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => ContentStatus::class,
            'publish_date' => 'immutable_date',
            'draft' => 'array',
            'guardian' => 'array',
            'major_revision' => 'boolean',
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

    /** @return BelongsTo<Offering, $this> */
    public function offering(): BelongsTo
    {
        return $this->belongsTo(Offering::class);
    }

    /** @return HasMany<Approval, $this> */
    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class);
    }

    /**
     * The plan line as the Copywriter receives it.
     *
     * @return array<string, mixed>
     */
    public function planLine(): array
    {
        return array_filter([
            'plan_item_key' => $this->plan_item_key,
            'date' => $this->publish_date->toDateString(),
            'platform' => $this->platform,
            'format' => $this->format,
            'pillar' => $this->pillar,
            'moment_key' => $this->moment_key,
            'offering_id' => $this->offering_id,
            'angle' => $this->angle,
            'cta' => $this->cta,
            'asset_hint' => $this->asset_hint,
        ], fn ($v) => $v !== null);
    }
}
