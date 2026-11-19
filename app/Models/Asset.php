<?php

namespace App\Models;

use App\Enums\AssetKind;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\URL;

/**
 * @property AssetKind $kind
 * @property ?list<string> $tags
 */
class Asset extends Model
{
    use HasUlids;

    protected $fillable = [
        'workspace_id', 'client_id', 'offering_id', 'kind', 'source', 'title', 'path', 'mime', 'width', 'height', 'bytes',
        'tags', 'has_people', 'has_children', 'consent_status', 'license', 'ai_generated',
    ];

    protected function casts(): array
    {
        return ['kind' => AssetKind::class, 'tags' => 'array', 'has_people' => 'boolean', 'has_children' => 'boolean', 'ai_generated' => 'boolean'];
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /** @return HasMany<AssetUsage, $this> */
    public function usages(): HasMany
    {
        return $this->hasMany(AssetUsage::class);
    }

    /** Usable in published content: children only with consent or when not a real photo (PRD §Pustaka aset). */
    public function isPublishable(): bool
    {
        if ($this->consent_status === 'refused' || $this->consent_status === 'pending') {
            return false;
        }

        return ! $this->has_children || $this->consent_status === 'granted' || in_array($this->source, ['stock', 'system'], true)
            || $this->kind === AssetKind::Illustration;
    }

    /** Short-lived signed URL for the dashboard preview (the disk is private). */
    public function previewUrl(int $minutes = 30): string
    {
        return URL::temporarySignedRoute('assets.show', now()->addMinutes($minutes), ['asset' => $this->ulid]);
    }
}
