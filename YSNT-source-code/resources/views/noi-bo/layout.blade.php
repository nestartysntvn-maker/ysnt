@php $C = \App\Support\Company::class; @endphp
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><meta name="robots" content="noindex, nofollow">
<title>{{ $title ?? 'Workplace' }} · {{ $C::get('brand_name') }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/noibo.css?v={{ @filemtime(public_path('css/noibo.css')) }}">
@if($C::color())<style>:root{--sea:{{ $C::color() }}}</style>@endif
</head>
<body>
@isset($wpMe)
@php
  // 5th field: also in the phone bottom bar (5 slots); the rest are reached from "Của tôi"
  $nav = [['/noi-bo', 'Bảng tin', 'M3 12l9-8 9 8M5 10v10h14V10', $wpUnread, true], ['/noi-bo/viec', 'Việc', 'M9 11l3 3 8-8M20 12v7H4V5h11', $wpTasks ?? 0, true],
    ['/noi-bo/quy-trinh', 'Quy trình', 'M6 4h9l3 3v13H6zM9 10h6M9 14h6', 0, true], ['/noi-bo/hoi-ai', 'Hỏi AI', 'M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12Z', 0, true],
    ['/noi-bo/danh-ba', 'Danh bạ', 'M16 11a4 4 0 1 0-8 0 4 4 0 0 0 8 0zM4 21c1-4 4-6 8-6s7 2 8 6', 0, false],
    ['/noi-bo/tuyen-dung', 'Tuyển dụng', 'M4 7h16v12H4zM9 7V5h6v2', 0, false], ['/noi-bo/toi', 'Của tôi', 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21c1-4 4-6 8-6s7 2 8 6', 0, true]];
@endphp
<div class="wp" id="wp">
  <main class="wp-main" id="main">
    @if(session('ok'))<div class="ok-msg" role="status">{{ session('ok') }}</div>@endif
    @yield('content')
  </main>
  <nav class="wp-nav" aria-label="Menu Workplace">
    <div class="wp-brand">@if($C::logoUrl())<img src="{{ $C::logoUrl() }}" alt="">@endif<span>Workplace<small>{{ $C::get('brand_name') }}</small></span></div>
    @foreach($nav as [$href, $label, $icon, $n, $mobile])
      <a href="{{ $href }}" class="{{ $mobile ? '' : 'more' }}" @if(request()->is(ltrim($href, '/')) || ($href !== '/noi-bo' && request()->is(ltrim($href, '/') . '/*'))) aria-current="page" @endif>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icon }}"/></svg>{{ $label }}@if($n)<span class="n">{{ $n }}</span>@endif</a>
    @endforeach
    <span class="sp"></span>
    <span class="side meta">{{ $wpMe->full_name }} · {{ $wpMe->code }}</span>
    <button class="side" type="button" onclick="const w=document.getElementById('wp');w.classList.toggle('left');try{localStorage.setItem('wp_side',w.classList.contains('left')?'left':'right')}catch{}">⇤ ⇥ Đổi menu trái / phải</button>
    <form method="post" action="/noi-bo/dang-xuat" class="more">@csrf<button class="side" type="submit">Đăng xuất</button></form>
  </nav>
</div>
<script>try{if(localStorage.getItem('wp_side')==='left')document.getElementById('wp').classList.add('left')}catch{}</script>
@else
@yield('content')
@endisset
</body>
</html>
