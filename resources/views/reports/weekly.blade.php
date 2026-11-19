{{-- F8.1 weekly report. Formal and neutral (CLAUDE.md rule 11). Every number comes from the KPI snapshot. --}}
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<style>
body{font-family:'Liberation Sans',Arial,sans-serif;color:#1f2937;font-size:11pt;line-height:1.45}
h1{font-size:18pt;margin:0 0 4pt} h2{font-size:12.5pt;margin:18pt 0 6pt;border-bottom:1px solid #d1d5db;padding-bottom:3pt}
.muted{color:#6b7280;font-size:9.5pt} table{width:100%;border-collapse:collapse;font-size:10pt}
th,td{text-align:left;padding:5pt 6pt;border-bottom:1px solid #e5e7eb} th{background:#f3f4f6}
td.n{text-align:right;font-variant-numeric:tabular-nums} .up{color:#047857}.down{color:#b91c1c}
ul{margin:4pt 0 0 14pt;padding:0} .primary td{font-weight:bold}
</style>
</head>
<body>
<div class="muted">Laporan mingguan · {{ $client->name }}</div>
<h1>{{ $narrative['headline'] ?? 'Ringkasan kinerja' }}</h1>
<div class="muted">Periode {{ $period['from'] }} s.d. {{ $period['to'] }} · dibandingkan {{ $period['compare_from'] }} s.d. {{ $period['compare_to'] }}</div>

@if (! empty($narrative['summary']))
<h2>Ringkasan</h2>
<p>{{ $narrative['summary'] }}</p>
@endif

<h2>Angka utama</h2>
<table>
<thead><tr><th>Metrik</th><th>Sumber</th><th class="n">Periode ini</th><th class="n">Sebelumnya</th><th class="n">Perubahan</th><th class="n">Target</th></tr></thead>
<tbody>
@foreach ($rows as $row)
<tr @class(['primary' => $row['is_primary_goal'] ?? false])>
    <td>{{ $row['label'] ?? $row['metric_key'] }}</td>
    <td>{{ $row['source'] }}</td>
    <td class="n">{{ number_format((float) $row['value'], 0, ',', '.') }}</td>
    <td class="n">{{ $row['previous'] === null ? '—' : number_format((float) $row['previous'], 0, ',', '.') }}</td>
    <td class="n {{ ($row['change_pct'] ?? 0) > 0 ? 'up' : (($row['change_pct'] ?? 0) < 0 ? 'down' : '') }}">{{ $row['change_pct'] === null ? '—' : number_format((float) $row['change_pct'], 1, ',', '.').'%' }}</td>
    <td class="n">{{ ($row['target'] ?? null) === null ? '—' : number_format((float) $row['target'], 0, ',', '.') }}</td>
</tr>
@endforeach
</tbody>
</table>

@foreach (['highlights' => 'Sorotan', 'concerns' => 'Perlu perhatian', 'next_steps' => 'Rencana minggu depan'] as $key => $title)
    @if (! empty($narrative[$key]))
    <h2>{{ $title }}</h2>
    <ul>@foreach ($narrative[$key] as $line)<li>{{ $line }}</li>@endforeach</ul>
    @endif
@endforeach

@if (! empty($quality))
<h2>Catatan data</h2>
<ul>@foreach ($quality as $line)<li class="muted">{{ $line }}</li>@endforeach</ul>
@endif

@if (empty($narrative))
<p class="muted">Komentar otomatis tidak tersedia minggu ini; angka di atas tetap dihitung dari data.</p>
@endif
</body>
</html>
