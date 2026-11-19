<?php

namespace App\Services\Artifacts\Generators;

use App\Enums\ArtifactType;
use App\Models\Client;
use App\Services\Artifacts\PrerequisiteMissing;
use App\Services\Llm\RoleRequest;

interface ArtifactGenerator
{
    public function type(): ArtifactType;

    /** @throws PrerequisiteMissing */
    public function request(Client $client): RoleRequest;

    /**
     * Overwrite anything that must come from the backend, not the model (T0 data such as sources or volumes).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function finalize(array $payload, Client $client, RoleRequest $request): array;
}
