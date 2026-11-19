<?php

namespace App\Models;

use App\Enums\PublishStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property PublishStatus $status
 * @property CarbonImmutable $scheduled_at
 * @property ?array<string, mixed> $qa
 */
class PublishJob extends Model
{
    use HasUlids;

    protected $fillable = [
        'workspace_id', 'client_id', 'content_item_id', 'platform', 'scheduled_at', 'mode', 'status', 'qa', 'external_post_id',
        'permalink', 'package_asset_id', 'reminder_token', 'reminded_at', 'published_at', 'error_code',
    ];

    protected function casts(): array
    {
        return [
            'status' => PublishStatus::class,
            'scheduled_at' => 'immutable_datetime',
            'reminded_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'qa' => 'array',
        ];
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /** @return BelongsTo<ContentItem, $this> */
    public function contentItem(): BelongsTo
    {
        return $this->belongsTo(ContentItem::class);
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<Asset, $this> */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'package_asset_id');
    }
}
