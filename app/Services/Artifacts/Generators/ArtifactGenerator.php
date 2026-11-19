<?php

namespace App\Services\Artifacts\Generators;

use App\Enums\ArtifactType;
use App\Models\Client;
use App\Services\Artifacts\PrerequisiteMissing;
use App\Services\Llm\RoleRequest;

interface ArtifactGenerator
{
    public function type(): ArtifactType;

    /**
     * @param  array<string, mixed>  $options  generator-specific input from the admin (e.g. month)
     *
     * @throws PrerequisiteMissing
     */
    public function request(Client $client, array $options = []): RoleRequest;

    /** @param  array<string, mixed>  $options */
    public function scopeRef(array $options): ?string;

    /**
     * Overwrite anything that must come from the backend, not the model (T0 data such as sources or volumes).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function finalize(array $payload, Client $client, RoleRequest $request): array;
}
