<?php

namespace App\Models;

use App\Enums\CredentialProvider;
use Illuminate\Database\Eloquent\Model;

/**
 * One credential field of one provider. `value` is encrypted with APP_KEY and hidden from
 * serialization; read it only through App\Services\Credentials\CredentialStore.
 */
class ProviderCredential extends Model
{
    protected $fillable = ['workspace_id', 'provider', 'key', 'value', 'last4', 'updated_by'];

    protected $hidden = ['value'];

    protected function casts(): array
    {
        return [
            'provider' => CredentialProvider::class,
            'value' => 'encrypted',
        ];
    }
}
