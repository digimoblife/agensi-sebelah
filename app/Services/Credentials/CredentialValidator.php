<?php

namespace App\Services\Credentials;

use App\Enums\CredentialProvider;
use SensitiveParameter;

/** Light format checks so a value pasted into the wrong field is caught before it is saved. */
final class CredentialValidator
{
    public function __construct(private readonly CredentialStore $store) {}

    /**
     * @param  array<string, string|null>  $submitted
     * @return array<string, string> field => error message (no values)
     */
    public function errors(CredentialProvider $provider, #[SensitiveParameter] array $submitted): array
    {
        $errors = [];
        foreach ($this->store->fields($provider) as $key => $field) {
            $value = trim((string) ($submitted[$key] ?? ''));
            if ($value === '') {
                continue; // empty keeps the stored value
            }
            if (isset($field['min']) && mb_strlen($value) < $field['min']) {
                $errors[$key] = "Terlalu pendek untuk {$field['label']}.";
            } elseif (isset($field['prefix']) && ! str_starts_with($value, $field['prefix'])) {
                $errors[$key] = "{$field['label']} biasanya diawali \"{$field['prefix']}\".";
            } elseif (isset($field['regex']) && preg_match($field['regex'], $value) !== 1) {
                $errors[$key] = "Format {$field['label']} tidak sesuai.";
            }
        }

        return $errors;
    }
}
