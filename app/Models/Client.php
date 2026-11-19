<?php

namespace App\Models;

use App\Enums\ClientStatus;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property ClientStatus $status
 * @property ?array<string, mixed> $onboarding
 * @property ?string $llm_budget_usd
 * @property ?string $media_budget_usd
 */
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    use HasUlids;

    protected $fillable = ['workspace_id', 'name', 'slug', 'status', 'timezone', 'primary_offering_type', 'onboarding', 'llm_budget_usd', 'media_budget_usd'];

    protected function casts(): array
    {
        return ['status' => ClientStatus::class, 'onboarding' => 'array'];
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @return BelongsTo<OfferingType, $this> */
    public function offeringType(): BelongsTo
    {
        return $this->belongsTo(OfferingType::class, 'primary_offering_type', 'key');
    }

    /** @return HasMany<Offering, $this> */
    public function offerings(): HasMany
    {
        return $this->hasMany(Offering::class);
    }

    /** @return HasMany<KnowledgeItem, $this> */
    public function knowledgeItems(): HasMany
    {
        return $this->hasMany(KnowledgeItem::class);
    }

    /** @return HasMany<KpiGoal, $this> */
    public function kpiGoals(): HasMany
    {
        return $this->hasMany(KpiGoal::class);
    }

    /** @return HasMany<ResearchInput, $this> */
    public function researchInputs(): HasMany
    {
        return $this->hasMany(ResearchInput::class);
    }

    /** @return HasMany<Artifact, $this> */
    public function artifacts(): HasMany
    {
        return $this->hasMany(Artifact::class);
    }

    /** @return HasMany<ArtifactVersion, $this> */
    public function artifactVersions(): HasMany
    {
        return $this->hasMany(ArtifactVersion::class);
    }

    /** @return HasMany<Asset, $this> */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    /** @return HasMany<ClientContact, $this> */
    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class);
    }

    /** @return HasMany<OAuthConnection, $this> */
    public function connections(): HasMany
    {
        return $this->hasMany(OAuthConnection::class);
    }

    public function connection(string $platform): ?OAuthConnection
    {
        return $this->connections()->where('platform', $platform)->first();
    }
}
