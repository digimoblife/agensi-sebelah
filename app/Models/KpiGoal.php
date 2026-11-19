<?php

namespace App\Models;

use App\Enums\KpiPeriod;
use Illuminate\Database\Eloquent\Model;

/** @property KpiPeriod $period */
class KpiGoal extends Model
{
    protected $fillable = ['workspace_id', 'client_id', 'metric_key', 'label', 'target_value', 'period', 'priority'];

    protected function casts(): array
    {
        return ['period' => KpiPeriod::class, 'priority' => 'integer'];
    }
}
