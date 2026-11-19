<?php

namespace App\Roles\Validators;

use App\Roles\OutputValidator;
use App\Services\Llm\RoleRequest;

/** Every cited source must be one the backend supplied (no invented sources). */
final class ResearchPackValidator implements OutputValidator
{
    public function errors(array $payload, RoleRequest $request): array
    {
        $allowed = array_column((array) ($request->task['sources'] ?? []), 'id');
        $errors = [];

        foreach (['competitors', 'trends'] as $section) {
            foreach ((array) ($payload[$section] ?? []) as $i => $entry) {
                foreach ((array) ($entry['source_ids'] ?? []) as $id) {
                    if (! in_array($id, $allowed, true)) {
                        $errors[] = "/{$section}/{$i}/source_ids: sumber {$id} tidak ada di daftar sumber";
                    }
                }
            }
        }

        return $errors;
    }
}
