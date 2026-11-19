@extends('render._base')
@section('content')
@if ($photo)
    <div style="position:absolute;inset:0;background:url('{{ $photo }}') center/cover no-repeat"></div>
    <div style="position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,.15) 0%,rgba(0,0,0,.7) 100%)"></div>
@else
    <div style="position:absolute;inset:0;background:linear-gradient(160deg,{{ $colors['secondary'] }} 0%,{{ $colors['background'] }} 100%)"></div>
@endif
<div style="position:absolute;left:80px;right:80px;top:38%;color:{{ $photo ? '#fff' : $colors['text'] }}">
    <div class="h" style="font-size:{{ (int) round($width * 0.09) }}px">{{ $headline }}</div>
    @if ($cta)<div style="margin-top:56px"><span class="cta">{{ $cta }}</span></div>@endif
</div>
@endsection
