<?php

namespace App\Services\Credentials;

use App\Enums\CredentialProvider;
use App\Enums\CredentialStatus;
use App\Models\ProviderCredential;
use App\Models\ProviderStatus;
use App\Models\Workspace;
use App\Services\Activity\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use SensitiveParameter;

/**
 * The only reader/writer of provider_credentials (docs/specs/credentials-settings.md).
 * Values are decrypted on demand and never cached, logged, or returned to the UI.
 */
class CredentialStore
{
    public function __construct(private readonly ActivityLogger $activity) {}

    /** @return array<string, array{label: string, secret: bool, required: bool, prefix?: string, min?: int, regex?: string, default?: string}> */
    public function fields(CredentialProvider $provider): array
    {
        /** @var array<string, array{label: string, secret: bool, required: bool}> $fields */
        $fields = config("credentials.providers.{$provider->value}.fields", []);

        return $fields;
    }

    public function get(CredentialProvider $provider, string $key): ?string
    {
        $value = $this->row($provider, $key)?->value;

        return is_string($value) && $value !== '' ? $value : ($this->fields($provider)[$key]['default'] ?? null);
    }

    /** @return array<string, string> every field with a value (stored or default) */
    public function values(CredentialProvider $provider): array
    {
        $values = [];
        foreach (array_keys($this->fields($provider)) as $key) {
            $value = $this->get($provider, $key);
            if ($value !== null) {
                $values[$key] = $value;
            }
        }

        return $values;
    }

    public function isComplete(CredentialProvider $provider): bool
    {
        $values = $this->values($provider);
        foreach ($this->fields($provider) as $key => $field) {
            if ($field['required'] && ! isset($values[$key])) {
                return false;
            }
        }

        return true;
    }

    /** Masked display value: secrets show only the last 4 characters; non-secrets are shown in full. */
    public function display(CredentialProvider $provider, string $key): ?string
    {
        $row = $this->row($provider, $key);
        if ($row === null) {
            return null;
        }

        return $this->fields($provider)[$key]['secret'] ? '••••••••'.$row->last4 : (string) $row->value;
    }

    /**
     * Save the submitted fields. An empty value keeps the stored one (spec: "Field kosong = nilai lama").
     *
     * @param  array<string, string|null>  $submitted
     * @return list<string> keys that changed
     */
    public function save(CredentialProvider $provider, #[SensitiveParameter] array $submitted): array
    {
        $changed = [];
        $workspaceId = Workspace::current()->id;

        DB::transaction(function () use ($provider, $submitted, $workspaceId, &$changed) {
            foreach ($this->fields($provider) as $key => $field) {
                $value = trim((string) ($submitted[$key] ?? ''));
                if ($value === '') {
                    continue;
                }

                $row = $this->row($provider, $key);
                if ($row !== null && $row->value === $value) {
                    continue;
                }

                ProviderCredential::query()->updateOrCreate(
                    ['workspace_id' => $workspaceId, 'provider' => $provider, 'key' => $key],
                    [
                        'value' => $value,
                        'last4' => $field['secret'] ? mb_substr($value, -4) : null,
                        'updated_by' => Auth::id(),
                    ],
                );
                $changed[] = $key;
            }

            if ($changed !== []) {
                $this->setStatus($provider, CredentialStatus::Untested, null, null, tested: false);
            }
        });

        if ($changed !== []) {
            $this->activity->log('credentials.updated', $provider->value, ['fields' => implode(',', $changed)]);
        }

        return $changed;
    }

    public function forget(CredentialProvider $provider): void
    {
        $workspaceId = Workspace::current()->id;
        ProviderCredential::query()->where(['workspace_id' => $workspaceId, 'provider' => $provider])->delete();
        ProviderStatus::query()->where(['workspace_id' => $workspaceId, 'provider' => $provider])->delete();
        $this->activity->log('credentials.deleted', $provider->value);
    }

    public function status(CredentialProvider $provider): ?ProviderStatus
    {
        return ProviderStatus::query()
            ->where(['workspace_id' => Workspace::current()->id, 'provider' => $provider])
            ->first();
    }

    /** A provider may be routed to unless it is incomplete or its last test failed. */
    public function isUsable(CredentialProvider $provider): bool
    {
        return $this->isComplete($provider) && $this->status($provider)?->status !== CredentialStatus::Failed;
    }

    public function recordTest(CredentialProvider $provider, ConnectionResult $result): void
    {
        $this->setStatus(
            $provider,
            $result->ok ? CredentialStatus::Ok : CredentialStatus::Failed,
            $result->message,
            $result->errorCode,
            tested: true,
        );
        $this->activity->log('credentials.tested', $provider->value, ['result' => $result->ok ? 'ok' : (string) $result->errorCode]);
    }

    private function setStatus(CredentialProvider $provider, CredentialStatus $status, ?string $message, ?string $errorCode, bool $tested): void
    {
        $attributes = ['status' => $status, 'message' => $message, 'last_error_code' => $errorCode];
        if ($tested) {
            $attributes['last_tested_at'] = now();
        }

        ProviderStatus::query()->updateOrCreate(
            ['workspace_id' => Workspace::current()->id, 'provider' => $provider],
            $attributes,
        );
    }

    private function row(CredentialProvider $provider, string $key): ?ProviderCredential
    {
        return ProviderCredential::query()
            ->where(['workspace_id' => Workspace::current()->id, 'provider' => $provider, 'key' => $key])
            ->first();
    }
}
