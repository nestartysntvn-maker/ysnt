@extends('layouts.app')
@section('content')
<div class="wrap">
  <div class="crumb"><a href="/">Trang chủ</a> › @if($cat)<a href="/san-pham">Sản phẩm</a> › {{ $cat->name }}@elseif($q !== null)Tìm kiếm @else Sản phẩm @endif</div>
  <div class="listing">
    <aside class="side" aria-label="Danh mục">
      <h3>Danh mục</h3>
      <a href="/san-pham" @if(!$cat && $q === null) aria-current="page" @endif>Tất cả<span>{{ $navTotal }}</span></a>
      @foreach($navCats as $c)<a href="/danh-muc/{{ $c->slug }}" @if($cat && $cat->id === $c->id) aria-current="page" @endif>{{ $c->name }}<span>{{ $c->products_count }}</span></a>@endforeach
      <h3>Nhãn hàng</h3>
      @foreach($brands as $b)<a href="{{ request()->fullUrlWithQuery(['nhan' => $brand === $b->slug ? null : $b->slug]) }}" @if($brand === $b->slug) aria-current="page" @endif>{{ $b->name }}</a>@endforeach
    </aside>
    <div>
      <h1 class="h1">@if($cat){{ $cat->name }}@elseif($q !== null)Kết quả cho “{{ $q }}”@else Tất cả sản phẩm @endif</h1>
      <div class="bar-tools">
        <span class="note">{{ $products->count() }} sản phẩm</span>
        <form method="get">@if($q !== null)<input type="hidden" name="q" value="{{ $q }}">@endif @if($brand)<input type="hidden" name="nhan" value="{{ $brand }}">@endif
          <label class="sr" for="sx">Sắp xếp</label>
          <select id="sx" name="sx" onchange="this.form.submit()">
            <option value="" @selected(!$sort)>Nổi bật</option><option value="gia-tang" @selected($sort==='gia-tang')>Giá thấp đến cao</option><option value="gia-giam" @selected($sort==='gia-giam')>Giá cao đến thấp</option><option value="ten" @selected($sort==='ten')>Tên A–Z</option>
          </select></form>
      </div>
      @if($products->count())
        <div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(200px,1fr))">@foreach($products as $p) @include('partials.card', ['p' => $p]) @endforeach</div>
      @else
        <div class="empty">@if($q !== null)Không có sản phẩm khớp “{{ $q }}”. Thử từ khác, ví dụ “súp”, “quà” hoặc “tinh chế”.@else Chưa có sản phẩm trong mục này.@endif <p><a class="btn btn-line" href="/san-pham">Xem tất cả sản phẩm</a></p></div>
      @endif
    </div>
  </div>
</div>
@endsection
