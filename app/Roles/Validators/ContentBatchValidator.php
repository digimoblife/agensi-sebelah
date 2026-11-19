<?php

namespace App\Roles\Validators;

use App\Roles\OutputValidator;
use App\Services\Llm\RoleRequest;

/** Exactly one item per requested plan item key (batching contract). */
final class ContentBatchValidator implements OutputValidator
{
    public function errors(array $payload, RoleRequest $request): array
    {
        $requested = array_values((array) ($request->task['plan_item_keys'] ?? []));
        if ($requested === []) {
            return [];
        }

        $returned = array_column((array) ($payload['items'] ?? []), 'plan_item_key');
        $errors = [];
        if ($missing = array_diff($requested, $returned)) {
            $errors[] = '/items: item untuk '.implode(', ', $missing).' belum ada';
        }
        if ($extra = array_diff($returned, $requested)) {
            $errors[] = '/items: item '.implode(', ', $extra).' tidak diminta';
        }
        if (count($returned) !== count(array_unique($returned))) {
            $errors[] = '/items: plan_item_key ganda';
        }

        return $errors;
    }
}
