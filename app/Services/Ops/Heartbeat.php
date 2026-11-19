<?php

namespace App\Services\Ops;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/** Last-run markers so pilot:check can tell whether the scheduler, poller and backups are alive. */
class Heartbeat
{
    public function beat(string $name): void
    {
        Cache::forever("heartbeat:{$name}", now()->toIso8601String());
    }

    public function last(string $name): ?CarbonImmutable
    {
        $value = Cache::get("heartbeat:{$name}");

        return is_string($value) ? CarbonImmutable::parse($value) : null;
    }
}
