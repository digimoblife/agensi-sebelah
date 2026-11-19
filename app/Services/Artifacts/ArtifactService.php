<?php

namespace App\Services\Artifacts;

use App\Enums\ArtifactStatus;
use App\Enums\ArtifactType;
use App\Models\Artifact;
use App\Models\ArtifactVersion;
use App\Models\Client;
use App\Models\User;
use App\Services\Schemas\SchemaRepository;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * Stores versioned artifacts (docs/ARTIFACTS.md). `meta` is always written by the backend, every payload is
 * validated before insert, and approved versions are never modified.
 */
class ArtifactService
{
    public function __construct(private readonly SchemaRepository $schemas) {}

    /** @param  array<string, mixed>  $payload */
    public function createVersion(
        Client $client,
        ArtifactType $type,
        array $payload,
        ?string $scopeRef = null,
        ?int $llmCallId = null,
        ?string $changeNote = null,
        ArtifactStatus $status = ArtifactStatus::Draft,
    ): ArtifactVersion {
        return DB::transaction(function () use ($client, $type, $payload, $scopeRef, $llmCallId, $changeNote, $status) {
            $artifact = Artifact::query()->firstOrCreate(
                ['client_id' => $client->id, 'type' => $type, 'scope_ref' => $scopeRef],
                ['workspace_id' => $client->workspace_id],
            );
            /** @var Artifact $artifact */
            $artifact = Artifact::query()->lockForUpdate()->findOrFail($artifact->id);

            $version = (int) $artifact->versions()->max('version') + 1;
            $payload['meta'] = array_merge($payload['meta'] ?? [], [
                'artifact_type' => $type->value,
                'schema_version' => 1,
                'client_id' => $client->id,
                'version' => $version,
            ]);

            $errors = $this->schemas->errors($type->schemaName(), $payload);
            if ($errors !== []) {
                throw new InvalidArgumentException("Invalid {$type->value} payload: ".implode('; ', array_slice($errors, 0, 5)));
            }

            $json = (string) json_encode($payload, JSON_UNESCAPED_UNICODE);

            /** @var ArtifactVersion $created */
            $created = $artifact->versions()->create([
                'client_id' => $client->id,
                'version' => $version,
                'schema_version' => 1,
                'status' => $status,
                'payload' => $payload,
                'token_estimate' => (int) ceil(mb_strlen($json) / (float) config('llm.chars_per_token')),
                'produced_by_llm_call_id' => $llmCallId,
                'change_note' => $changeNote,
            ]);

            $artifact->update(['current_version_id' => $created->id]);

            return $created;
        });
    }

    public function approve(ArtifactVersion $version, User $user): ArtifactVersion
    {
        if (! in_array($version->status, [ArtifactStatus::Draft, ArtifactStatus::InReview], true)) {
            throw new LogicException("Only draft or in-review versions can be approved (got {$version->status->value}).");
        }

        return DB::transaction(function () use ($version, $user) {
            ArtifactVersion::query()
                ->where('artifact_id', $version->artifact_id)
                ->where('status', ArtifactStatus::Approved)
                ->update(['status' => ArtifactStatus::Superseded]);

            $version->update(['status' => ArtifactStatus::Approved, 'approved_by' => $user->id, 'approved_at' => now()]);

            return $version;
        });
    }

    /** The version other roles may read: the latest approved one. */
    public function approved(Client $client, ArtifactType $type, ?string $scopeRef = null): ?ArtifactVersion
    {
        return ArtifactVersion::query()
            ->whereHas('artifact', fn ($q) => $q->where(['client_id' => $client->id, 'type' => $type, 'scope_ref' => $scopeRef]))
            ->where('status', ArtifactStatus::Approved)
            ->orderByDesc('version')
            ->first();
    }
}
