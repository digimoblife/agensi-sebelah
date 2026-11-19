<?php

namespace App\Roles\Validators;

use App\Roles\OutputValidator;
use App\Services\Llm\RoleRequest;

/** Dates inside the month, unique keys, and only pillars/moments/offerings/platforms that were supplied. */
final class ContentPlanValidator implements OutputValidator
{
    public function errors(array $payload, RoleRequest $request): array
    {
        $month = (string) ($request->task['bulan'] ?? '');
        $pillars = array_column((array) ($request->artifacts['brand_brief']['pillars'] ?? []), 'key');
        $moments = array_column((array) ($request->task['momen'] ?? []), 'key');
        $offerings = array_column((array) ($request->task['penawaran'] ?? []), 'id');
        $formats = (array) ($request->task['platform_dan_format'] ?? []);

        $errors = [];
        $keys = [];
        foreach ((array) ($payload['items'] ?? []) as $i => $item) {
            $at = "/items/{$i}";
            if (! str_starts_with((string) ($item['date'] ?? ''), $month.'-')) {
                $errors[] = "{$at}/date: harus di bulan {$month}";
            }
            if (in_array($item['key'] ?? null, $keys, true)) {
                $errors[] = "{$at}/key: {$item['key']} ganda";
            }
            $keys[] = $item['key'] ?? null;
            if ($pillars !== [] && ! in_array($item['pillar'] ?? null, $pillars, true)) {
                $errors[] = "{$at}/pillar: pakai salah satu dari ".implode(', ', $pillars);
            }
            if (($item['moment_key'] ?? null) !== null && ! in_array($item['moment_key'], $moments, true)) {
                $errors[] = "{$at}/moment_key: {$item['moment_key']} tidak ada di kalender bulan ini";
            }
            if (($item['offering_id'] ?? null) !== null && ! in_array($item['offering_id'], $offerings, true)) {
                $errors[] = "{$at}/offering_id: penawaran {$item['offering_id']} tidak dikenal";
            }
            $platform = (string) ($item['platform'] ?? '');
            if (! in_array($item['format'] ?? null, (array) ($formats[$platform] ?? []), true)) {
                $errors[] = "{$at}: kombinasi {$platform}/".($item['format'] ?? '?').' tidak diminta';
            }
        }

        return array_slice($errors, 0, 20);
    }
}
