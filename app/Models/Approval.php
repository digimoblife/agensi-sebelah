<?php

namespace App\Models;

use App\Enums\ApprovalDecision;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/** @property ApprovalDecision $decision */
class Approval extends Model
{
    use HasUlids;

    protected $fillable = ['workspace_id', 'client_id', 'content_item_id', 'channel', 'decision', 'comment', 'reason_tags', 'is_major', 'decided_by', 'decided_at'];

    protected function casts(): array
    {
        return ['decision' => ApprovalDecision::class, 'reason_tags' => 'array', 'is_major' => 'boolean', 'decided_at' => 'immutable_datetime'];
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }
}
