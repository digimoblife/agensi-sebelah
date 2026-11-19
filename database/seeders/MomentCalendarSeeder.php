<?php

namespace Database\Seeders;

use App\Models\Moment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * F9.7 Indonesian marketing moments for this year and the next two. Islamic holidays and school holidays are
 * estimates (marked approximate) until the government decree (SKB) is published; the admin corrects them in
 * the dashboard. Re-running is safe (keyed by moment key + start date).
 */
class MomentCalendarSeeder extends Seeder
{
    /** Estimated dates (hisab); confirm against the SKB 3 Menteri each year. */
    private const ISLAMIC = [
        2026 => ['ramadan' => ['2026-02-18', '2026-03-19'], 'idul_fitri' => ['2026-03-20', '2026-03-21'], 'idul_adha' => ['2026-05-27', '2026-05-27']],
        2027 => ['ramadan' => ['2027-02-08', '2027-03-09'], 'idul_fitri' => ['2027-03-10', '2027-03-11'], 'idul_adha' => ['2027-05-17', '2027-05-17']],
        2028 => ['ramadan' => ['2028-01-28', '2028-02-25'], 'idul_fitri' => ['2028-02-26', '2028-02-27'], 'idul_adha' => ['2028-05-05', '2028-05-05']],
    ];

    public function run(): void
    {
        $first = CarbonImmutable::now('Asia/Jakarta')->year;

        foreach (range($first, $first + 2) as $year) {
            $this->put('tahun_baru', 'Tahun Baru', 'national', "{$year}-01-01", "{$year}-01-01");
            $this->put('kemerdekaan', 'Hari Kemerdekaan (17 Agustus)', 'national', "{$year}-08-17", "{$year}-08-17");
            $this->put('harbolnas_1111', 'Harbolnas 11.11', 'shopping', "{$year}-11-11", "{$year}-11-11");
            $this->put('harbolnas_1212', 'Harbolnas 12.12', 'shopping', "{$year}-12-12", "{$year}-12-12");
            $this->put('natal', 'Natal', 'religious', "{$year}-12-25", "{$year}-12-25");
            $this->put('libur_akhir_tahun', 'Libur sekolah & akhir tahun', 'school', "{$year}-12-20", "{$year}-12-31", true);
            $this->put('libur_kenaikan_kelas', 'Libur kenaikan kelas', 'school', "{$year}-06-22", "{$year}-07-12", true);

            foreach (range(1, 12) as $month) {
                $start = CarbonImmutable::parse(sprintf('%d-%02d-25', $year, $month));
                $this->put('gajian', 'Tanggal gajian', 'payday', $start->toDateString(), $start->endOfMonth()->toDateString());
            }

            foreach (self::ISLAMIC[$year] ?? [] as $key => [$start, $end]) {
                $name = ['ramadan' => 'Ramadan', 'idul_fitri' => 'Idul Fitri (Lebaran)', 'idul_adha' => 'Idul Adha'][$key];
                $this->put($key, $name, 'religious', $start, $end, true, 'Perkiraan; sesuaikan dengan SKB pemerintah.');
            }
        }
    }

    private function put(string $key, string $name, string $kind, string $start, string $end, bool $approximate = false, ?string $notes = null): void
    {
        Moment::query()->updateOrCreate(
            ['key' => $key, 'date_start' => $start],
            ['name' => $name, 'kind' => $kind, 'date_end' => $end, 'approximate' => $approximate, 'notes' => $notes],
        );
    }
}
