<?php

namespace App\Roles;

use App\Services\Llm\RoleRequest;

/** Role-specific checks that a JSON Schema cannot express. Runs after schema validation in the gateway. */
interface OutputValidator
{
    /**
     * @param  array<string, mixed>  $payload
     * @return list<string> errors (empty = valid)
     */
    public function errors(array $payload, RoleRequest $request): array;
}
