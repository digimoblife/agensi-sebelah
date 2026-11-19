<?php

namespace App\Services\Llm;

use App\Enums\TaskPriority;

/**
 * Input of one role run. Only the artifacts the role declares in config/roles.php are accepted
 * (narrow input contract). Facts are approved knowledge-base items: the only allowed source of facts.
 */
final readonly class RoleRequest
{
    /**
     * @param  array<string, array<string, mixed>>  $artifacts  artifact type => approved payload
     * @param  list<array{id: int, text: string}>  $facts
     * @param  array<string, mixed>  $task  small task-specific input
     */
    public function __construct(
        public int $clientId,
        public string $roleKey,
        public string $taskRef,
        public array $artifacts = [],
        public array $facts = [],
        public array $task = [],
        public TaskPriority $priority = TaskPriority::Normal,
        public ?int $forceModelId = null,
    ) {}
}
