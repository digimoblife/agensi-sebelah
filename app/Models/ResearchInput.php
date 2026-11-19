<?php

namespace App\Models;

use App\Enums\ResearchInputKind;
use Illuminate\Database\Eloquent\Model;

/** @property ResearchInputKind $kind */
class ResearchInput extends Model
{
    protected $fillable = ['workspace_id', 'client_id', 'kind', 'ref', 'note', 'keyword_volume'];

    protected function casts(): array
    {
        return ['kind' => ResearchInputKind::class];
    }
}
