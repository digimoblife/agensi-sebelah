<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MetricDaily extends Model
{
    public $timestamps = false;

    protected $table = 'metrics_daily';

    protected $fillable = ['client_id', 'source', 'entity_type', 'entity_ref', 'date', 'metric_key', 'value', 'content_item_id', 'pulled_at'];

    protected function casts(): array
    {
        return ['date' => 'immutable_date', 'pulled_at' => 'immutable_datetime'];
    }
}
