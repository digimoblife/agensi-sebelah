{{-- Shared shell for render templates. Everything is local: Gotenberg has no internet access. --}}
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<style>
{!! $fontCss !!}
*{box-sizing:border-box;margin:0;padding:0}
html,body{width:{{ $width }}px;height:{{ $height }}px;overflow:hidden}
body{background:{{ $colors['background'] }};color:{{ $colors['text'] }};font-family:'{{ $bodyFont }}',system-ui,sans-serif;position:relative}
.h{font-family:'{{ $headingFont }}',Georgia,serif;font-weight:700;line-height:1.08;letter-spacing:-.01em;overflow-wrap:break-word}
.logo{position:absolute;max-width:{{ (int) round($width * 0.16) }}px;max-height:{{ (int) round($width * 0.1) }}px;object-fit:contain}
.logo.top_left{top:56px;left:56px}.logo.top_right{top:56px;right:56px}.logo.bottom_left{bottom:56px;left:56px}
.logo.bottom_right{bottom:56px;right:56px}.logo.bottom_center{bottom:56px;left:50%;transform:translateX(-50%)}
.cta{display:inline-block;background:{{ $colors['primary'] }};color:{{ $colors['background'] }};font-weight:700;border-radius:999px;padding:22px 44px;font-size:{{ (int) round($width * 0.034) }}px}
.page{position:absolute;top:56px;right:56px;font-size:28px;opacity:.7}
</style>
</head>
<body>
@yield('content')
@if ($logo)<img class="logo {{ $logoPlacement }}" src="{{ $logo }}" alt="">@endif
@if ($page)<div class="page">{{ $page }}</div>@endif
</body>
</html>
