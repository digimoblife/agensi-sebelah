<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CarbonImmutable $expires_at
 * @property ?CarbonImmutable $awaiting_comment_at
 * @property ?CarbonImmutable $decided_at
 */
class ApprovalRequest extends Model
{
    protected $fillable = ['token', 'content_item_id', 'client_contact_id', 'chat_id', 'message_id', 'expires_at', 'awaiting_comment_at', 'decision', 'decided_at'];

    protected function casts(): array
    {
        return ['chat_id' => 'integer', 'message_id' => 'integer', 'expires_at' => 'immutable_datetime', 'awaiting_comment_at' => 'immutable_datetime', 'decided_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<ContentItem, $this> */
    public function contentItem(): BelongsTo
    {
        return $this->belongsTo(ContentItem::class);
    }

    /** @return BelongsTo<ClientContact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(ClientContact::class, 'client_contact_id');
    }
}
