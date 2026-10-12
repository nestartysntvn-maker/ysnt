@extends('layouts.app')
@section('content')
<div class="wrap">
  <div class="crumb"><a href="/">Trang chủ</a> › {{ $crumb }}</div>
  @if($heading)<h1 class="h1">{{ $heading }}</h1>@endif
  @if(!empty($lead))<p class="lead">{{ $lead }}</p>@endif
  <div id="{{ $rootId }}"><p class="note">Đang tải…</p></div>
  <noscript><p>Trình duyệt cần bật JavaScript để dùng giỏ hàng.</p></noscript>
</div>
@endsection
