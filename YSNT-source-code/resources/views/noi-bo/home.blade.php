@extends('noi-bo.layout', ['title' => 'Bảng tin'])
@section('content')
<h1>Chào {{ $wpMe->alias ?: $wpMe->full_name }}</h1>
@php $ty = fn ($v) => \App\Support\Targets::ty((int) $v); $pc = fn ($a, $t) => $t ? min(100, round($a / $t * 100)) : 0; @endphp
@php
  // bar = the stretch goal; filled part = achieved; the dark tick = the committed target
  $bar = fn ($a, $t, $s, $h) => '<div style="position:relative;height:' . $h . 'px;background:var(--sea50);border-radius:6px;overflow:hidden" role="img" aria-label="Đạt ' . $pc($a, $t) . '% cam kết">'
    . '<div style="height:100%;width:' . $pc($a, $s) . '%;background:var(--sea);border-radius:6px"></div>'
    . ($s > $t ? '<div style="position:absolute;top:0;bottom:0;left:' . round($t / $s * 100, 1) . '%;width:2px;background:var(--ink)" title="Cam kết"></div>' : '') . '</div>';
@endphp
@if($goal)
<section class="card" aria-label="Mục tiêu năm {{ $goal['year'] }}" style="border-left:4px solid var(--sea)">
  <div class="meta">Mục tiêu chung của công ty</div>
  <h2 style="margin:4px 0 2px">Năm {{ $goal['year'] }}: cam kết {{ $ty($goal['target']) }}@if($goal['stretch'] > $goal['target']) · phấn đấu {{ $ty($goal['stretch']) }}@endif</h2>
  @if(! $goal['started'])
    <p class="meta" style="margin:0 0 10px">Bắt đầu tính từ 01/01/{{ $goal['year'] }}, còn {{ $goal['daysToStart'] }} ngày. Quý 1: {{ $ty($goal['quarters'][1]) }} · Quý 2: {{ $ty($goal['quarters'][2]) }} · Quý 3: {{ $ty($goal['quarters'][3]) }} · Quý 4: {{ $ty($goal['quarters'][4]) }}.</p>
  @else
    <p class="meta" style="margin:0 0 6px">Đã đạt {{ $ty($goal['actual']) }} ({{ $pc($goal['actual'], $goal['target']) }}%), đã qua {{ $goal['elapsedPct'] }}% thời gian của năm.</p>
    {!! $bar($goal['actual'], $goal['target'], $goal['stretch'], 10) !!}
  @endif
  <div class="grid2" style="grid-template-columns:repeat(3,minmax(0,1fr));margin-top:12px;gap:10px">
    @foreach($goal['groups'] as $g)<div><div class="meta">{{ $g['label'] }}</div><b>{{ $ty($g['a']) }}</b><span class="meta"> / {{ $ty($g['t']) }}@if($g['s'] > $g['t']) · phấn đấu {{ $ty($g['s']) }}@endif</span>
      <div style="margin-top:4px">{!! $bar($g['a'], $g['t'], $g['s'], 6) !!}</div></div>@endforeach
  </div>
  @if($goal['stretch'] > $goal['target'])<p class="meta" style="margin:8px 0 0">Vạch đậm trên thanh là mức cam kết, hết thanh là mức phấn đấu.</p>@endif
  @php
    $m = $goal['month'];
    $line = $m['label'] . ': chỉ tiêu ' . $ty($m['t']) . ($m['s'] > $m['t'] ? ', phấn đấu ' . $ty($m['s']) : '') . ($goal['started'] ? ', đã đạt ' . $ty($m['a']) : '') . '.';
  @endphp
  <p class="meta" style="margin:6px 0 0">{{ $line }}@if($goal['mine'] !== null) Bạn đóng góp: <b>{{ $ty($goal['mine']) }}</b> qua mã giới thiệu của bạn.@endif</p>
</section>
@endif
@if($tasks->isNotEmpty())
<a class="card" href="/noi-bo/viec" style="display:block;text-decoration:none;color:inherit">
  <b>Bạn có {{ $tasks->count() }} việc đang mở</b>@php $late = $tasks->filter->isOverdue()->count(); @endphp @if($late)<span class="pill" style="background:#fde2e2;color:var(--red);margin-left:6px">{{ $late }} quá hạn</span>@endif
  @foreach($tasks->take(3) as $t)<div class="meta" style="margin-top:4px">• {{ $t->title }} · hạn {{ \App\Hr\Tasks::when($t->due_at) }}</div>@endforeach
</a>
@endif
@forelse($items as $r)
  @php $a = $r->announcement; @endphp
  <article class="card {{ $r->read_at ? '' : 'unread' }}">
    <div class="meta">{{ $a->created_at->format('H:i d/m/Y') }} @if($a->pinned)<span class="pill amb">Ghim</span>@endif @unless($r->read_at)<span class="pill">Mới</span>@endunless</div>
    <h2 style="margin:6px 0">{{ $a->title }}</h2>
    <div class="body">{{ $a->body }}</div>
    @if($a->link)<p><a href="{{ $a->link }}" target="_blank" rel="noopener">Mở liên kết</a></p>@endif
    @unless($r->read_at)<form method="post" action="/noi-bo/thong-bao/{{ $r->id }}/da-doc" style="margin-top:10px">@csrf<button class="btn line">Đã đọc</button></form>@endunless
  </article>
@empty
  <div class="card">Chưa có thông báo nào. Thông báo của công ty sẽ hiện ở đây.</div>
@endforelse
@endsection
