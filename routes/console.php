<?php

use App\Services\Ops\Heartbeat;
use Illuminate\Support\Facades\Schedule;

// ADR 0003: every task is idempotent and catches up when the Mac wakes up.
Schedule::command('publish:due')->everyMinute()->withoutOverlapping()->onSuccess(fn () => app(Heartbeat::class)->beat('scheduler'));
Schedule::command('backup:run --verify')->dailyAt('02:00')->timezone('Asia/Jakarta')->withoutOverlapping();
Schedule::command('metrics:pull')->dailyAt('03:00')->timezone('Asia/Jakarta')->withoutOverlapping();
// Hourly from Monday 08:00: generates any missing report for last week (a missed Monday is picked up later).
Schedule::command('reports:weekly')->hourly()->between('08:00', '23:00')->timezone('Asia/Jakarta')->withoutOverlapping();
