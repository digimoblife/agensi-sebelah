<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property CarbonImmutable $date_start
 * @property CarbonImmutable $date_end
 */
class Moment extends Model
{
    protected $table = 'moment_calendar';

    protected $fillable = ['key', 'name', 'kind', 'date_start', 'date_end', 'approximate', 'notes'];

    protected function casts(): array
    {
        return ['date_start' => 'immutable_date', 'date_end' => 'immutable_date', 'approximate' => 'boolean'];
    }
}
