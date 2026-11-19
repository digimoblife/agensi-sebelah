<?php

namespace App\Roles;

use App\Enums\Tier;
use InvalidArgumentException;

/** Reads config/roles.php. A role is a prompt + schema, not a running agent (PRD §Struktur tim AI). */
class RoleRegistry
{
    public function get(string $key): RoleDefinition
    {
        $config = config("roles.{$key}");
        if (! is_array($config)) {
            throw new InvalidArgumentException("Unknown AI role [{$key}].");
        }

        return new RoleDefinition(
            key: $key,
            label: (string) $config['label'],
            tier: Tier::from((string) $config['tier']),
            promptKey: (string) $config['prompt'],
            promptVersion: (int) $config['prompt_version'],
            outputSchema: (string) $config['output_schema'],
            artifactInputs: array_values((array) $config['artifact_inputs']),
            usesFacts: (bool) $config['uses_facts'],
            capabilities: array_values((array) $config['capabilities']),
            effort: isset($config['effort']) ? (string) $config['effort'] : null,
            maxOutputTokens: (int) $config['max_output_tokens'],
            expectedOutputTokens: (int) $config['expected_output_tokens'],
            allowDowngrade: (bool) $config['allow_downgrade'],
            validator: $this->validator($config['validator'] ?? null),
            why: (string) ($config['why'] ?? ''),
        );
    }

    /** @return class-string<OutputValidator>|null */
    private function validator(mixed $class): ?string
    {
        if ($class === null) {
            return null;
        }
        if (! is_string($class) || ! is_subclass_of($class, OutputValidator::class)) {
            throw new InvalidArgumentException('Role validator must implement '.OutputValidator::class.'.');
        }

        return $class;
    }

    /** @return array<string, string> key => label */
    public function options(): array
    {
        return collect((array) config('roles'))->map(fn (array $role) => (string) $role['label'])->all();
    }
}
