@extends('render._base')
@section('content')
@if ($photo)
    <div style="position:absolute;inset:0;background:url('{{ $photo }}') center/cover no-repeat"></div>
    <div style="position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,0) 35%,rgba(0,0,0,.72) 100%)"></div>
@else
    <div style="position:absolute;inset:0;background:{{ $colors['secondary'] }}"></div>
    <div style="position:absolute;left:-12%;bottom:-18%;width:70%;height:55%;border-radius:50%;background:{{ $colors['accent'] ?? $colors['primary'] }};opacity:.35"></div>
@endif
<div style="position:absolute;left:72px;right:72px;bottom:{{ $logo ? 210 : 120 }}px;color:{{ $photo ? '#fff' : $colors['text'] }}">
    <div class="h" style="font-size:{{ (int) round($width * (mb_strlen($headline) > 28 ? 0.075 : 0.095)) }}px">{{ $headline }}</div>
    @if ($cta)<div style="margin-top:40px"><span class="cta">{{ $cta }}</span></div>@endif
</div>
@endsection
