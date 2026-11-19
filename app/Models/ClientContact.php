<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientContact extends Model
{
    protected $fillable = ['workspace_id', 'client_id', 'name', 'can_approve', 'telegram_chat_id', 'invite_code_hash', 'invite_expires_at', 'linked_at'];

    protected $hidden = ['invite_code_hash'];

    protected function casts(): array
    {
        return ['can_approve' => 'boolean', 'telegram_chat_id' => 'integer', 'invite_expires_at' => 'immutable_datetime', 'linked_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
