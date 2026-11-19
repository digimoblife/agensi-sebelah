<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * F1.2. Tokens are encrypted and hidden; read them only inside App\Services\Connections.
 *
 * @property ?array<string, mixed> $meta
 * @property ?CarbonImmutable $expires_at
 */
class OAuthConnection extends Model
{
    protected $table = 'oauth_connections';

    protected $fillable = ['workspace_id', 'client_id', 'platform', 'account_ref', 'account_name', 'access_token', 'refresh_token', 'expires_at', 'scopes', 'meta', 'status', 'last_error_code'];

    protected $hidden = ['access_token', 'refresh_token'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'immutable_datetime',
            'scopes' => 'array',
            'meta' => 'array',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->account_ref !== null && $this->access_token !== null;
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
