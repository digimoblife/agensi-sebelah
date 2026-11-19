<?php

namespace App\Models;

use App\Enums\CredentialProvider;
use App\Enums\CredentialStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property CredentialProvider $provider
 * @property CredentialStatus $status
 * @property ?string $message
 * @property ?string $last_error_code
 * @property ?CarbonImmutable $last_tested_at
 */
class ProviderStatus extends Model
{
    protected $fillable = ['workspace_id', 'provider', 'status', 'message', 'last_error_code', 'last_tested_at'];

    protected function casts(): array
    {
        return [
            'provider' => CredentialProvider::class,
            'status' => CredentialStatus::class,
            'last_tested_at' => 'immutable_datetime',
        ];
    }
}
