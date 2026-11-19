<?php

namespace App\Services\Schemas;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use RuntimeException;

/**
 * Artifact JSON Schemas (resources/schemas/artifacts). Two views of each schema:
 *  - the full schema, validated locally with opis (the source of truth);
 *  - a provider-safe copy for structured output: $refs inlined, unsupported keywords (lengths, patterns,
 *    numeric bounds, item counts) removed, and `meta` dropped because the backend fills it.
 */
class SchemaRepository
{
    public const ID_PREFIX = 'https://agensi-sebelah.local/schemas/';

    /** Keywords providers' structured-output modes do not accept; enforced locally instead. */
    private const UNSUPPORTED = [
        '$schema', '$id', '$defs', 'title', 'default', 'minLength', 'maxLength', 'pattern', 'minimum', 'maximum',
        'exclusiveMinimum', 'exclusiveMaximum', 'multipleOf', 'minItems', 'maxItems', 'uniqueItems',
    ];

    private ?Validator $validator = null;

    /** @var array<string, array<string, mixed>> */
    private array $cache = [];

    public function exists(string $name): bool
    {
        return is_file($this->path($name));
    }

    /** @return list<string> human-readable errors, empty when valid */
    public function errors(string $name, mixed $data): array
    {
        $result = $this->validator()->validate(
            json_decode((string) json_encode($data, JSON_UNESCAPED_UNICODE)),
            self::ID_PREFIX.$name.'.schema.json',
        );

        $error = $result->error();
        if ($error === null) {
            return [];
        }

        $errors = [];
        foreach ((new ErrorFormatter)->format($error, true) as $pointer => $messages) {
            foreach ((array) $messages as $message) {
                $errors[] = ($pointer === '' ? '/' : $pointer).': '.$message;
            }
        }

        return array_slice($errors, 0, 20);
    }

    public function hasMeta(string $name): bool
    {
        return isset($this->load($name)['properties']['meta']);
    }

    /** @return array<string, mixed> */
    public function providerSchema(string $name): array
    {
        $schema = $this->load($name);
        $resolved = $this->strip($this->inline($schema, $name));

        unset($resolved['properties']['meta']);
        if (isset($resolved['required'])) {
            $resolved['required'] = array_values(array_diff($resolved['required'], ['meta']));
        }

        return $resolved;
    }

    /** @return array<string, mixed> */
    private function load(string $name): array
    {
        if (isset($this->cache[$name])) {
            return $this->cache[$name];
        }

        $path = $this->path($name);
        if (! is_file($path)) {
            throw new RuntimeException("Unknown schema [{$name}].");
        }

        /** @var array<string, mixed> $schema */
        $schema = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        return $this->cache[$name] = $schema;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function inline(array $node, string $file, int $depth = 0): array
    {
        if ($depth > 20) {
            throw new RuntimeException('Schema $ref nesting too deep (recursive schemas are not supported).');
        }

        if (isset($node['$ref']) && is_string($node['$ref'])) {
            [$refFile, $pointer] = array_pad(explode('#', $node['$ref'], 2), 2, '');
            $targetFile = $refFile === '' ? $file : str_replace('.schema.json', '', $refFile);
            $target = $this->load($targetFile);
            foreach (array_filter(explode('/', $pointer)) as $segment) {
                $target = $target[$segment] ?? throw new RuntimeException("Unresolvable \$ref {$node['$ref']}.");
            }
            unset($node['$ref']);

            return $this->inline(array_merge($target, $node), $targetFile, $depth + 1);
        }

        foreach ($node as $key => $value) {
            if (is_array($value) && $key !== 'enum' && $key !== 'required') {
                $node[$key] = $this->inline($value, $file, $depth + 1);
            }
        }

        return $node;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function strip(array $node): array
    {
        foreach (self::UNSUPPORTED as $keyword) {
            unset($node[$keyword]);
        }

        foreach ($node as $key => $value) {
            if (is_array($value) && ! in_array($key, ['enum', 'required', 'type'], true)) {
                $node[$key] = $this->strip($value);
            }
        }

        return $node;
    }

    private function validator(): Validator
    {
        if ($this->validator === null) {
            $this->validator = new Validator;
            $this->validator->resolver()?->registerPrefix(self::ID_PREFIX, resource_path('schemas/artifacts/'));
        }

        return $this->validator;
    }

    private function path(string $name): string
    {
        return resource_path("schemas/artifacts/{$name}.schema.json");
    }
}
