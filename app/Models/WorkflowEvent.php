<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['client_id', 'subject_type', 'subject_id', 'from_status', 'to_status', 'actor', 'reason'];
}
