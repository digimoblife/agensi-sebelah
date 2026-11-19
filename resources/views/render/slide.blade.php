@extends('render._base')
@section('content')
<div style="position:absolute;top:0;left:0;width:22px;height:100%;background:{{ $colors['primary'] }}"></div>
<div style="position:absolute;left:96px;right:96px;top:50%;transform:translateY(-55%)">
    <div class="h" style="font-size:{{ (int) round($width * 0.072) }}px;color:{{ $colors['primary'] }}">{{ $headline }}</div>
    @if ($body)<div style="margin-top:36px;font-size:{{ (int) round($width * 0.038) }}px;line-height:1.4">{{ $body }}</div>@endif
    @if ($cta)<div style="margin-top:56px"><span class="cta">{{ $cta }}</span></div>@endif
</div>
@endsection
