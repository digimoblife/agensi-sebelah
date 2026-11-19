<?php

namespace App\Roles\Validators;

use App\Roles\OutputValidator;
use App\Services\Llm\RoleRequest;

/** One review per draft that was sent, and a verdict consistent with its issues. */
final class GuardianReviewValidator implements OutputValidator
{
    public function errors(array $payload, RoleRequest $request): array
    {
        $requested = array_column((array) ($request->task['drafts'] ?? []), 'plan_item_key');
        $returned = array_column((array) ($payload['items'] ?? []), 'plan_item_key');
        $errors = [];

        if (($missing = array_diff($requested, $returned)) !== []) {
            $errors[] = '/items: review untuk '.implode(', ', $missing).' belum ada';
        }
        if (($extra = array_diff($returned, $requested)) !== []) {
            $errors[] = '/items: '.implode(', ', $extra).' tidak diminta';
        }
        foreach ((array) ($payload['items'] ?? []) as $i => $item) {
            $hasMajor = in_array('major', array_column((array) ($item['issues'] ?? []), 'severity'), true);
            if ($hasMajor && ($item['verdict'] ?? null) === 'pass') {
                $errors[] = "/items/{$i}/verdict: ada isu major, verdict harus fail";
            }
        }

        return $errors;
    }
}
