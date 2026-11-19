<?php

namespace App\Models;

use App\Enums\KnowledgeKind;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property KnowledgeKind $kind
 * @property ?CarbonImmutable $valid_until
 * @property ?CarbonImmutable $approved_at
 */
class KnowledgeItem extends Model
{
    protected $fillable = ['workspace_id', 'client_id', 'offering_id', 'kind', 'title', 'body', 'source', 'valid_until', 'approved_at', 'approved_by'];

    protected function casts(): array
    {
        return ['kind' => KnowledgeKind::class, 'valid_until' => 'immutable_date', 'approved_at' => 'immutable_datetime'];
    }

    /**
     * Approved and not expired: the only items a role may receive.
     *
     * @param  Builder<KnowledgeItem>  $query
     */
    public function scopeUsable(Builder $query): void
    {
        $query->whereNotNull('approved_at')
            ->where(fn (Builder $q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', now('Asia/Jakarta')->toDateString()));
    }

    /** @return BelongsTo<Offering, $this> */
    public function offering(): BelongsTo
    {
        return $this->belongsTo(Offering::class);
    }
}
