<?php

namespace App\Roles\Validators;

use App\Roles\OutputValidator;
use App\Services\Llm\RoleRequest;

/** Comments only on metrics that exist in the snapshot. */
final class WeeklyReportValidator implements OutputValidator
{
    public function errors(array $payload, RoleRequest $request): array
    {
        $known = array_column((array) ($request->artifacts['kpi_snapshot']['rows'] ?? []), 'metric_key');
        $errors = [];
        foreach ((array) ($payload['metric_notes'] ?? []) as $i => $note) {
            if (! in_array($note['metric_key'] ?? null, $known, true)) {
                $errors[] = "/metric_notes/{$i}/metric_key: {$note['metric_key']} tidak ada di KPI Snapshot";
            }
        }

        return $errors;
    }
}
