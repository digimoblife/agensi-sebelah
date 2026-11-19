<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'activity_log';

    protected $fillable = ['workspace_id', 'user_id', 'action', 'subject', 'properties', 'ip'];

    protected function casts(): array
    {
        return ['properties' => 'array'];
    }
}
