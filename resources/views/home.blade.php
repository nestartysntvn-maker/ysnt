@extends('layouts.app')
@section('content')
<section class="hero" id="ask">
  <div class="wrap">
    <div class="ask"><p class="k">Chạm vào chữ gạch dưới để đổi</p><p class="say">Tôi muốn mua yến cho <button>bố mẹ</button>, <button>khoảng 1 triệu</button>.</p></div>
    <div class="picks"></div>
  </div>
</section>
<script type="application/json" id="home-data">{!! json_encode($homeData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
<div class="wrap">
  <div class="proof">
    <div><b>Hơn 10 năm</b>sản xuất, kinh doanh yến sào</div>
    <div><b>2,2 ha</b>nhà máy tại Khánh Hòa</div>
    <div><b>FDA, USDA</b>chứng nhận xuất khẩu Mỹ</div>
    <div><b>ISO 22000, HACCP</b>an toàn thực phẩm</div>
  </div>

  <section class="sec">
    <div class="sec-head"><div><h2 class="h2">Chọn theo dòng sản phẩm</h2><p class="lead" style="margin:0">Từ tổ yến nguyên tổ đến các món yến dùng liền mỗi ngày.</p></div><a href="/san-pham">Xem tất cả {{ $navTotal }} sản phẩm</a></div>
    <div class="tiles">
      @foreach($tiles as $t)
      <a class="tile" href="/danh-muc/{{ $t['slug'] }}">@if($t['img'])<img loading="lazy" src="{{ $t['img'] }}" alt="">@endif<span>{{ $t['name'] }}<small>{{ $t['count'] }} sản phẩm · từ {{ number_format($t['min'], 0, ',', '.') }}đ</small></span></a>
      @endforeach
    </div>
  </section>

  <section class="sec">
    <div class="sec-head"><div><h2 class="h2">Được mua nhiều</h2></div><a href="/san-pham">Xem thêm</a></div>
    <div class="grid">@foreach($best as $p) @include('partials.card', ['p' => $p]) @endforeach</div>
  </section>

  <section class="sec">
    <div class="sec-head"><div><h2 class="h2">Quà biếu đóng hộp sẵn</h2><p class="lead" style="margin:0">Hộp đẹp, có túi xách, hợp để biếu gia đình và đối tác.</p></div><a href="/danh-muc/bo-qua-tang">Xem tất cả quà biếu</a></div>
    <div class="grid">@foreach($gifts as $p) @include('partials.card', ['p' => $p]) @endforeach</div>
  </section>
</div>

<section class="visit">
  <div class="wrap">
    <div>
      <h2>Ghé nhà yến ngay trung tâm Nha Trang</h2>
      <p>01 Nguyễn Thiện Thuật, ngay trung tâm thành phố. Xem chim yến làm tổ, thử yến chưng và mua quà mang về trong ngày.</p>
      <p style="display:flex;gap:10px;flex-wrap:wrap"><a class="btn btn-white" href="/tham-quan">Xem thông tin tham quan</a><a class="btn" style="border:1.5px solid rgba(255,255,255,.6);color:#fff" target="_blank" rel="noopener" href="https://www.google.com/maps/search/?api=1&query=01+Nguy%E1%BB%85n+Thi%E1%BB%87n+Thu%E1%BA%ADt+Nha+Trang">Chỉ đường</a></p>
    </div>
    <ul>
      <li><b>Xem nhà yến thật</b>Tận mắt nơi chim yến làm tổ, hiểu vì sao tổ yến có giá trị.</li>
      <li><b>Mua tại showroom</b>Đủ các dòng sản phẩm và bộ quà đóng hộp sẵn.</li>
      <li><b>Đi theo đoàn</b>Công ty lữ hành và hướng dẫn viên gọi hotline để hẹn lịch cho đoàn.</li>
    </ul>
  </div>
</section>
@endsection
