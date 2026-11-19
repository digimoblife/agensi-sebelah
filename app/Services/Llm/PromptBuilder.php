<?php

namespace App\Services\Llm;

use App\Roles\RoleDefinition;
use InvalidArgumentException;

/**
 * Deterministic prompt assembly. Byte-identical output for identical input keeps the provider prefix cache warm:
 * keys are sorted, facts ordered by id, and nothing time-dependent is included.
 */
final class PromptBuilder
{
    /** The stable, cacheable client context: Brand Brief (+ Visual Rules) and facts. */
    public function cachedContext(RoleDefinition $role, RoleRequest $request): string
    {
        $parts = [];
        foreach (['brand_brief' => 'Brand Brief', 'visual_rules' => 'Visual Rules'] as $type => $title) {
            if (isset($request->artifacts[$type])) {
                $parts[] = "## {$title}\n".$this->json($request->artifacts[$type]);
            }
        }

        if ($role->usesFacts) {
            $facts = $request->facts;
            usort($facts, fn (array $a, array $b) => $a['id'] <=> $b['id']);
            $lines = array_map(fn (array $f) => "[kb:{$f['id']}] ".str_replace("\n", ' ', $f['text']), $facts);
            $parts[] = "## Fakta klien (knowledge base)\nHanya fakta di bawah ini yang boleh dipakai. Sebutkan rujukannya (kb:<id>).\n"
                .($lines === [] ? '(belum ada fakta)' : implode("\n", $lines));
        }

        return implode("\n\n", $parts);
    }

    public function task(RoleDefinition $role, RoleRequest $request): string
    {
        $parts = [];
        foreach ($request->artifacts as $type => $payload) {
            if (in_array($type, ['brand_brief', 'visual_rules'], true)) {
                continue;
            }
            $parts[] = '## '.str_replace('_', ' ', ucfirst($type))."\n".$this->json($payload);
        }
        $parts[] = "## Tugas\n".$this->json($request->task === [] ? ['instruksi' => 'Kerjakan sesuai peran.'] : $request->task);
        $parts[] = 'Balas hanya dengan JSON yang sesuai schema output. Jangan menambahkan teks lain.';

        return implode("\n\n", $parts);
    }

    /** Enforces the narrow input contract (PRD principle 6). */
    public function assertInputs(RoleDefinition $role, RoleRequest $request): void
    {
        $extra = array_diff(array_keys($request->artifacts), $role->artifactInputs);
        if ($extra !== []) {
            throw new InvalidArgumentException("Role [{$role->key}] does not accept artifacts: ".implode(', ', $extra));
        }
    }

    /** @param  array<mixed>  $data */
    private function json(array $data): string
    {
        return (string) json_encode($this->sortKeys($data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    private function sortKeys(array $data): array
    {
        if (! array_is_list($data)) {
            ksort($data);
        }

        return array_map(fn ($v) => is_array($v) ? $this->sortKeys($v) : $v, $data);
    }
}
