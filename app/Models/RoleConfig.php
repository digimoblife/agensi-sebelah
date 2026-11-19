<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoleConfig extends Model
{
    protected $fillable = ['workspace_id', 'role_key', 'primary_model_id', 'fallback_model_id', 'max_cost_per_call_usd'];

    /** @return BelongsTo<ModelCatalogEntry, $this> */
    public function primaryModel(): BelongsTo
    {
        return $this->belongsTo(ModelCatalogEntry::class, 'primary_model_id');
    }

    /** @return BelongsTo<ModelCatalogEntry, $this> */
    public function fallbackModel(): BelongsTo
    {
        return $this->belongsTo(ModelCatalogEntry::class, 'fallback_model_id');
    }
}
