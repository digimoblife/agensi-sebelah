<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * F9.1 profile: what conversion, KPIs, pillars, CTAs and assets a kind of offering implies.
 *
 * @property string $key
 * @property string $label
 * @property string $primary_conversion
 * @property list<string> $conversion_events
 * @property list<string> $default_kpis
 * @property list<array{key: string, name: string}> $content_pillars
 * @property list<string> $ctas
 * @property list<string> $required_assets
 * @property list<string> $compliance_notes
 */
class OfferingType extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'label', 'primary_conversion', 'conversion_events', 'default_kpis', 'content_pillars', 'ctas', 'required_assets', 'compliance_notes'];

    protected function casts(): array
    {
        return [
            'conversion_events' => 'array',
            'default_kpis' => 'array',
            'content_pillars' => 'array',
            'ctas' => 'array',
            'required_assets' => 'array',
            'compliance_notes' => 'array',
        ];
    }

    /** @return array<string, mixed> compact profile handed to roles */
    public function profile(): array
    {
        return [
            'jenis' => $this->label,
            'konversi_utama' => $this->primary_conversion,
            'event_konversi' => $this->conversion_events,
            'kpi_bawaan' => $this->default_kpis,
            'pilar_bawaan' => $this->content_pillars,
            'cta_bawaan' => $this->ctas,
            'catatan_kepatuhan' => $this->compliance_notes,
        ];
    }
}
