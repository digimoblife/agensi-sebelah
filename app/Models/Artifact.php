<?php

namespace App\Models;

use App\Enums\ArtifactType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property ArtifactType $type
 * @property ?string $scope_ref
 */
class Artifact extends Model
{
    protected $fillable = ['workspace_id', 'client_id', 'type', 'scope_ref', 'current_version_id'];

    protected function casts(): array
    {
        return ['type' => ArtifactType::class];
    }

    /** @return HasMany<ArtifactVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(ArtifactVersion::class);
    }

    /** @return BelongsTo<ArtifactVersion, $this> */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(ArtifactVersion::class, 'current_version_id');
    }
}
