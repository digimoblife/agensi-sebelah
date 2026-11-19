<?php

namespace App\Filament\Widgets;

use App\Enums\LlmCallStatus;
use App\Models\LlmCall;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** F8.2 (basic): AI cost this month. Per-client and per-role breakdown comes with the clients module. */
class LlmCostOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $start = CarbonImmutable::now('Asia/Jakarta')->startOfMonth()->utc();
        $month = LlmCall::query()->where('created_at', '>=', $start);

        $cost = (float) (clone $month)->sum('cost_usd');
        $calls = (clone $month)->count();
        $failed = (clone $month)->whereNotIn('status', [LlmCallStatus::Ok, LlmCallStatus::CacheHit])->count();
        $input = (int) (clone $month)->sum('input_tokens');
        $cached = (int) (clone $month)->sum('cached_input_tokens');

        return [
            Stat::make('Biaya AI bulan ini', '$'.number_format($cost, 2)),
            Stat::make('Panggilan bulan ini', number_format($calls))->description("{$failed} gagal / tidak valid"),
            Stat::make('Input ter-cache', $input + $cached > 0 ? round($cached / ($input + $cached) * 100).'%' : '—')
                ->description('Makin tinggi makin hemat'),
        ];
    }
}
