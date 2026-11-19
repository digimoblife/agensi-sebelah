<?php

namespace App\Services\Calendar;

use App\Models\Moment;
use Carbon\CarbonImmutable;

/** F9.7: moments that overlap a month, in the compact form the Content Strategist receives. */
class MomentCalendar
{
    /** @return list<array{key: string, nama: string, mulai: string, selesai: string, perkiraan: bool}> */
    public function forMonth(string $month): array
    {
        $start = CarbonImmutable::parse($month.'-01')->startOfMonth();
        $end = $start->endOfMonth();

        return array_values(Moment::query()
            ->where('date_start', '<=', $end->toDateString())
            ->where('date_end', '>=', $start->toDateString())
            ->orderBy('date_start')
            ->get()
            ->map(fn (Moment $m) => [
                'key' => $m->key,
                'nama' => $m->name,
                'mulai' => $m->date_start->toDateString(),
                'selesai' => $m->date_end->toDateString(),
                'perkiraan' => (bool) $m->approximate,
            ])->all());
    }
}
