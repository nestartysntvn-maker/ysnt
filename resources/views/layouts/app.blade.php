<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
@php
  $C = \App\Support\Company::class; $S = \App\Models\AppSetting::class;
  $index = \App\Support\Seo::indexable() && empty($noindex);
  $pixel = $S::get('meta_pixel_id'); $ga = $S::get('seo_ga4_id'); $gtm = $S::get('seo_gtm_id');
  $metaDesc = $desc ?? $S::get('seo_default_desc', 'Tổ yến từ nhà yến Khánh Hòa: tổ yến tinh chế, tổ yến ăn liền, tổ yến chưng sẵn, quà biếu. Giao toàn quốc, ghé nhà yến tại 01 Nguyễn Thiện Thuật, Nha Trang.');
@endphp
<title>{{ $title ?? $C::get('brand_name') }}</title>
<meta name="description" content="{{ $metaDesc }}">
<meta name="robots" content="{{ $index ? 'index, follow, max-image-preview:large' : 'noindex, nofollow' }}">
<meta name="csrf-token" content="{{ csrf_token() }}">
@if($v = $S::get('seo_google_verify'))<meta name="google-site-verification" content="{{ $v }}">@endif
@if($v = $S::get('seo_bing_verify'))<meta name="msvalidate.01" content="{{ $v }}">@endif
@isset($canonical)<link rel="canonical" href="{{ $canonical }}">@endisset
<meta property="og:site_name" content="{{ $C::get('brand_name') }}">
<meta property="og:type" content="{{ $ogType ?? 'website' }}">
<meta property="og:title" content="{{ $title ?? $C::get('brand_name') }}">
<meta property="og:description" content="{{ $metaDesc }}">
@isset($canonical)<meta property="og:url" content="{{ $canonical }}">@endisset
@isset($ogImage)<meta property="og:image" content="{{ $ogImage }}">@endisset
@if($C::color())<style>:root{--sea:{{ $C::color() }};--sea2:{{ $C::color() }}}</style>@endif
<script type="application/ld+json">{!! json_encode(array_merge($C::jsonLd(), $jsonLd ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@if($gtm)<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer',@json($gtm));</script>@endif
@if($ga)<script async src="https://www.googletagmanager.com/gtag/js?id={{ urlencode($ga) }}"></script><script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag('js',new Date());gtag('config',@json($ga));</script>@endif
@if($pixel)<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init',@json($pixel));fbq('track','PageView');@isset($pixelEvent)fbq('track',@json($pixelEvent[0]),@json($pixelEvent[1]));@endisset</script>@endif
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/site.css?v={{ $assetV }}">
</head>
<body class="{{ $bodyClass ?? '' }}">
@if($gtm)<noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ urlencode($gtm) }}" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>@endif
<a class="sr" href="#main">Bỏ qua menu</a>
<header class="hd">
  <div class="wrap">
    <a class="logo" href="/">@if($C::logoUrl())<img src="{{ $C::logoUrl() }}" alt="" style="height:40px;width:auto">@else<span class="seal" aria-hidden="true">Yến</span>@endif<span><b>{{ $C::get('brand_name') }}</b><small>{{ $C::get('brand_tagline') }}</small></span></a>
    <nav class="nav" aria-label="Menu chính">
      <div class="dd"><button aria-haspopup="true">Sản phẩm</button>
        <div class="dd-panel">
          <a href="/san-pham"><b>Tất cả sản phẩm</b><span>{{ $navTotal }}</span></a>
          @foreach($navCats as $c)<a href="/danh-muc/{{ $c->slug }}">{{ $c->name }}<span>{{ $c->products_count }}</span></a>@endforeach
        </div></div>
      <a href="/danh-muc/bo-qua-tang" @if(request()->is('danh-muc/bo-qua-tang')) aria-current="page" @endif>Quà biếu</a>
      <a href="/tham-quan" @if(request()->is('tham-quan')) aria-current="page" @endif>Tham quan nhà yến</a>
      <a href="/gioi-thieu" @if(request()->is('gioi-thieu')) aria-current="page" @endif>Giới thiệu</a>
      <a href="/lien-he" @if(request()->is('lien-he')) aria-current="page" @endif>Liên hệ</a>
    </nav>
    <div class="hd-tools">
      <form class="hd-search" action="/tim-kiem" role="search"><input name="q" value="{{ request('q') }}" placeholder="Tìm tổ yến, súp, quà…" aria-label="Tìm sản phẩm"><button>Tìm</button></form>
      <a class="ib track" href="/tra-cuu-don">Tra cứu đơn</a>
      <a class="ib" href="/gio-hang" aria-label="Giỏ hàng">Giỏ <span class="badge" data-cart-count>0</span></a>
      <button class="ib burger" aria-expanded="false" aria-controls="mnav" onclick="const m=document.getElementById('mnav');m.hidden=!m.hidden;this.setAttribute('aria-expanded',!m.hidden)">Menu</button>
    </div>
  </div>
  <div class="mnav" id="mnav" hidden>
    <form action="/tim-kiem" role="search"><input name="q" placeholder="Tìm sản phẩm" aria-label="Tìm sản phẩm"><button class="btn btn-sea">Tìm</button></form>
    <a href="/san-pham">Tất cả sản phẩm</a>
    @foreach($navCats as $c)<a href="/danh-muc/{{ $c->slug }}">{{ $c->name }}</a>@endforeach
    <a href="/tham-quan">Tham quan nhà yến</a><a href="/gioi-thieu">Giới thiệu</a><a href="/lien-he">Liên hệ</a><a href="/tra-cuu-don">Tra cứu đơn hàng</a>
  </div>
  @if($refCode)<div class="refbar"><div class="wrap">Bạn đang mua qua mã giới thiệu <b>{{ $refCode }}</b>. Đơn hàng sẽ ghi nhận cho người giới thiệu.</div></div>@endif
</header>
<main id="main">@yield('content')</main>
<footer class="ft">
  <div class="wrap">
    <div>
      <a class="logo" href="/">@if($C::logoUrl())<img src="{{ $C::logoUrl() }}" alt="" style="height:40px;width:auto">@else<span class="seal" aria-hidden="true">Yến</span>@endif<span><b>{{ $C::get('brand_name') }}</b><small>{{ $C::get('company_name') }}</small></span></a>
      <p style="margin-top:14px">Trụ sở: {{ $C::get('hq_address') }}</p>
      <p>Mã số thuế {{ $C::get('tax_code') }}, {{ $C::get('tax_issued') }}</p>
      <p>Hotline <a href="tel:{{ $C::tel() }}" style="display:inline">{{ $C::get('hotline_sales') }}</a> · <a href="mailto:{{ $C::get('email_public') }}" style="display:inline">{{ $C::get('email_public') }}</a></p>
      @if($soc = $C::socials())<p>@foreach($soc as $lbl => $u)<a href="{{ $u }}" target="_blank" rel="noopener" style="display:inline;margin-right:12px">{{ $lbl }}</a>@endforeach</p>@endif
    </div>
    <div><h4>Sản phẩm</h4>@foreach($navCats->take(7) as $c)<a href="/danh-muc/{{ $c->slug }}">{{ $c->name }}</a>@endforeach<a href="/san-pham">Xem tất cả</a></div>
    <div><h4>Mua hàng</h4><a href="/tra-cuu-don">Tra cứu đơn hàng</a><a href="/chinh-sach/van-chuyen">Chính sách vận chuyển</a><a href="/chinh-sach/thanh-toan">Hình thức thanh toán</a><a href="/chinh-sach/doi-tra">Bảo hành, đổi trả</a><a href="/chinh-sach/bao-mat">Chính sách bảo mật</a></div>
    <div><h4>{{ $C::get('brand_name') }}</h4><a href="/gioi-thieu">Giới thiệu</a><a href="/tham-quan">Tham quan nhà yến</a><a href="/lien-he">Liên hệ, chi nhánh</a><a href="/tuyen-dung">Tuyển dụng</a><a href="/noi-bo">Workplace nhân viên</a></div>
  </div>
  <div class="ft-bot"><div class="wrap" style="display:block;padding-top:0;padding-bottom:0">© {{ $C::get('company_name') }}.@unless($index) Bản chạy thử trên {{ request()->getHost() }}.@endunless</div></div>
</footer>
<script>window.YS_REF = @json($refCode); window.YS_SHOWROOM = @json($C::get('showroom_address'));</script>
<script src="/js/site.js?v={{ $assetV }}"></script>
@if(\App\Http\Controllers\ChatController::enabled())<link rel="stylesheet" href="/css/chat.css?v={{ $assetV }}"><script src="/js/chat.js?v={{ $assetV }}" defer data-brand="{{ $C::get('brand_name') }}" data-hotline="{{ $C::get('hotline_sales') }}" id="ys-chat-js"></script>@endif
@stack('scripts')
</body>
</html>
