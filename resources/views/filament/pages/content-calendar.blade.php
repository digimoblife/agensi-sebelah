<x-filament-panels::page>
    {{-- Inline styles: the panel theme only ships the classes Filament itself uses. --}}
    <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap">
        <x-filament::button color="gray" size="sm" wire:click="shift(-1)" icon="heroicon-m-chevron-left">Sebelumnya</x-filament::button>
        <div style="font-weight:600;font-size:1.1rem;min-width:10rem;text-align:center">{{ $label }}</div>
        <x-filament::button color="gray" size="sm" wire:click="shift(1)" icon="heroicon-m-chevron-right" icon-position="after">Berikutnya</x-filament::button>
        <x-filament::input.wrapper style="min-width:14rem">
            <x-filament::input.select wire:model.live="client">
                <option value="">Semua klien</option>
                @foreach ($clients as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
        <x-filament::link :href="$listUrl" size="sm">Buka daftar konten</x-filament::link>
    </div>

    <div style="overflow-x:auto">
        <div style="display:grid;grid-template-columns:repeat(7,minmax(8.5rem,1fr));gap:1px;background:rgba(127,127,127,.25);border:1px solid rgba(127,127,127,.25);border-radius:.5rem;overflow:hidden;min-width:60rem">
            @foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $dow)
                <div style="padding:.4rem .5rem;font-size:.75rem;font-weight:600;background:rgba(127,127,127,.08)">{{ $dow }}</div>
            @endforeach
            @foreach ($days as $day)
                <div style="min-height:7.5rem;padding:.4rem .5rem;background:var(--color-white, #fff);{{ $day['inMonth'] ? '' : 'opacity:.45' }}" class="dark:bg-gray-900">
                    <div style="display:flex;justify-content:space-between;font-size:.75rem">
                        <span style="font-weight:600">{{ $day['date']->day }}</span>
                        @if ($day['payday'])
                            <span title="Tanggal gajian">💰</span>
                        @endif
                    </div>
                    @foreach ($day['moments'] as $moment)
                        <div style="font-size:.7rem;margin-top:.2rem;color:#b45309">★ {{ $moment }}</div>
                    @endforeach
                    @foreach ($day['items'] as $item)
                        <div style="margin-top:.3rem;font-size:.72rem;line-height:1.25">
                            <x-filament::badge size="sm" :color="$item->status->color()">{{ $item->platform }} · {{ $item->format }}</x-filament::badge>
                            <div title="{{ $item->angle }}" style="margin-top:.1rem">{{ \Illuminate\Support\Str::limit($item->angle, 48) }}</div>
                            @unless ($client)
                                <div style="opacity:.6">{{ $item->client->name }}</div>
                            @endunless
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
