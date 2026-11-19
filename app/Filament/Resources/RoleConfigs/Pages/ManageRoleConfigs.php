<?php

namespace App\Filament\Resources\RoleConfigs\Pages;

use App\Enums\Tier;
use App\Filament\Resources\RoleConfigs\RoleConfigResource;
use App\Models\Workspace;
use App\Roles\RoleRegistry;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class ManageRoleConfigs extends ManageRecords
{
    protected static string $resource = RoleConfigResource::class;

    /** Guidance shown above the table: model class per role, and what is not an AI role yet (image/video). */
    public function getSubheading(): Htmlable
    {
        $registry = app(RoleRegistry::class);
        $rows = collect($registry->options())->keys()->map(function (string $key) use ($registry) {
            $role = $registry->get($key);
            $colour = match ($role->tier) {
                Tier::T3 => '#dc2626', Tier::T2 => '#d97706', default => '#059669',
            };

            return '<tr><td style="padding:4px 12px 4px 0;font-weight:600">'.e($role->label).'</td>'
                .'<td style="padding:4px 12px 4px 0;white-space:nowrap;color:'.$colour.';font-weight:600">'.e($role->tier->modelClass()).'</td>'
                .'<td style="padding:4px 0;opacity:.8">'.e($role->why).'</td></tr>';
        })->implode('');

        return new HtmlString(
            '<div style="font-size:.875rem;line-height:1.45">'
            .'<p style="margin-bottom:6px">Kelas model yang disarankan per peran. High-end: '.e(Tier::T3->examples()).'. '
            .'Menengah: '.e(Tier::T2->examples()).'. Murah: '.e(Tier::T1->examples()).'. Model cadangan boleh satu kelas di bawahnya.</p>'
            .'<table style="width:100%;border-collapse:collapse;margin-bottom:6px">'.$rows.'</table>'
            .'<p style="opacity:.8"><b>Gambar dan video:</b> belum ada peran AI yang membuat gambar atau video di MVP. Visual dibuat dari template '
            .'(tanpa AI, tanpa biaya token). Pembuatan gambar AI (mis. GPT Image, Gemini) dan video AI (mis. Veo) masuk Fase 2; '
            .'modelnya nanti ditandai kemampuan "Buat gambar"/"Buat video" di Katalog model. Tidak ada peran MVP yang butuh kemampuan "Baca gambar".</p>'
            .'</div>'
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->mutateDataUsing(fn (array $data) => $data + ['workspace_id' => Workspace::current()->id]),
        ];
    }
}
