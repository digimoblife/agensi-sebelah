<?php

namespace App\Roles;

use App\Enums\Tier;

final readonly class RoleDefinition
{
    /**
     * @param  list<string>  $artifactInputs
     * @param  list<string>  $capabilities
     */
    public function __construct(
        public string $key,
        public string $label,
        public Tier $tier,
        public string $promptKey,
        public int $promptVersion,
        public string $outputSchema,
        public array $artifactInputs,
        public bool $usesFacts,
        public array $capabilities,
        public ?string $effort,
        public int $maxOutputTokens,
        public int $expectedOutputTokens,
        public bool $allowDowngrade,
        /** @var class-string<OutputValidator>|null */
        public ?string $validator = null,
        public string $why = '',
    ) {}
}
