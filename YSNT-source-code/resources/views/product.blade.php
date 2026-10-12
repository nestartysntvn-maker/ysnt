@extends('layouts.app')
@section('content')
<div class="wrap">
  <div class="crumb"><a href="/">Trang chủ</a> › <a href="/danh-muc/{{ $p->category?->slug }}">{{ $p->category?->name }}</a> › {{ $p->name }}</div>
  <div class="pdp">
    <div class="gal">@if($img)<img src="{{ $img }}" alt="{{ $p->name }}">@endif</div>
    <div>
      <h1>{{ $p->name }}</h1>
      <div class="meta">{{ $p->brand?->name }} · {{ $p->category?->name }}</div>
      <div id="pd-pick"><div class="price">{{ number_format($p->variants->min('list_price'), 0, ',', '.') }}đ</div></div>
    </div>
  </div>
  <script type="application/json" id="pd-data">{!! json_encode($pd, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
  @if(count($descBlocks))
  <section class="desc"><h2 class="h2">Thông tin sản phẩm</h2>
    @foreach($descBlocks as $i => $h)<div data-desc="{{ $i }}" @if($i > 0) hidden @endif>{!! $h !!}</div>@endforeach
  </section>
  @endif
  @if($related->count())
  <section class="sec"><div class="sec-head"><h2 class="h2">Cùng dòng sản phẩm</h2><a href="/danh-muc/{{ $p->category?->slug }}">Xem tất cả</a></div>
    <div class="grid">@foreach($related as $r) @include('partials.card', ['p' => $r]) @endforeach</div></section>
  @endif
</div>
@endsection
