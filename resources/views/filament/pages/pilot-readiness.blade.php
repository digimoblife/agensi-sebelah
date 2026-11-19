<x-filament-panels::page>
    @php($icon = fn ($status) => ['ok' => ['heroicon-m-check-circle', 'success'], 'warn' => ['heroicon-m-exclamation-triangle', 'warning'], 'fail' => ['heroicon-m-x-circle', 'danger']][$status])

    <x-filament::section heading="Sistem" description="Baris merah menghalangi pilot; kuning boleh menyusul. Sama dengan perintah: php artisan pilot:check">
        <div style="display:grid;gap:.4rem">
            @foreach ($system as $check)
                @php([$name, $color] = $icon($check->status))
                <div style="display:flex;gap:.6rem;align-items:flex-start">
                    <x-filament::icon :icon="$name" @class(['h-5 w-5', 'text-success-500' => $color === 'success', 'text-warning-500' => $color === 'warning', 'text-danger-500' => $color === 'danger']) style="flex:none;width:1.25rem;height:1.25rem" />
                    <div>
                        <span style="font-weight:600">{{ $check->group }} · {{ $check->label }}</span>
                        @if ($check->detail)<span style="opacity:.7"> — {{ $check->detail }}</span>@endif
                        @if ($check->hint)<div style="font-size:.85rem;opacity:.75">{{ $check->hint }}</div>@endif
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>

    @forelse ($clients as $row)
        @php($gate = $row['gate'])
        <x-filament::section :heading="$row['client']->name" collapsible>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(11rem,1fr));gap:.75rem;margin-bottom:1rem">
                @foreach ([
                    ['Minggu berjalan', $gate['weeks'].' / '.$weeksRequired, $gate['weeks'] >= $weeksRequired],
                    ['Disetujui tanpa revisi besar', $gate['clean_pct'] === null ? '—' : $gate['clean_pct'].'% ('.$gate['clean'].'/'.$gate['decided'].')', ($gate['clean_pct'] ?? 0) >= $approvalTarget],
                    ['Biaya AI bulan ini', '$'.number_format($gate['llm_cost_month'] + $gate['media_cost_month'], 2).' · '.$gate['calls_month'].' panggilan', $gate['calls_month'] > 0],
                    ['Biaya per konten terbit', $gate['cost_per_content'] === null ? '—' : '$'.number_format($gate['cost_per_content'], 3), null],
                    ['vs Paperclip', $gate['savings_pct'] === null ? 'Isi baseline' : ($gate['savings_pct'] >= 0 ? 'hemat ' : 'lebih mahal ').abs($gate['savings_pct']).'%', $gate['savings_pct'] === null ? null : $gate['savings_pct'] > 0],
                ] as [$label, $value, $good])
                    <div style="border:1px solid rgba(127,127,127,.25);border-radius:.5rem;padding:.6rem .75rem">
                        <div style="font-size:.75rem;opacity:.7">{{ $label }}</div>
                        <div style="font-weight:600;{{ $good === true ? 'color:#059669' : ($good === false ? 'color:#d97706' : '') }}">{{ $value }}</div>
                    </div>
                @endforeach
            </div>
            <div style="margin-bottom:.75rem">
                <x-filament::badge :color="$gate['passed'] ? 'success' : 'gray'">{{ $gate['passed'] ? 'Gerbang MVP terpenuhi' : 'Gerbang MVP belum terpenuhi' }}</x-filament::badge>
                @if ($gate['first_live'])<span style="font-size:.85rem;opacity:.7"> · terbit pertama {{ $gate['first_live'] }}</span>@endif
            </div>
            <div style="display:grid;gap:.35rem">
                @foreach ($row['checks'] as $check)
                    @php([$name, $color] = $icon($check->status))
                    <div style="display:flex;gap:.6rem;align-items:flex-start">
                        <x-filament::icon :icon="$name" @class(['text-success-500' => $color === 'success', 'text-warning-500' => $color === 'warning', 'text-danger-500' => $color === 'danger']) style="flex:none;width:1.1rem;height:1.1rem" />
                        <div style="font-size:.9rem">{{ $check->label }}@if ($check->detail)<span style="opacity:.7"> — {{ $check->detail }}</span>@endif
                            @if ($check->hint)<span style="opacity:.7"> · {{ $check->hint }}</span>@endif</div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @empty
        <x-filament::section><p>Belum ada klien aktif. Tambahkan klien pilot di menu Klien.</p></x-filament::section>
    @endforelse
</x-filament-panels::page>
