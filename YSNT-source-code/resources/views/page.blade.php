@extends('layouts.app')
@section('content')
<div class="wrap">
  <div class="crumb"><a href="/">Trang chủ</a> › {{ $heading }}</div>
  <h1 class="h1">{{ $heading }}</h1>
  @if(!empty($lead))<p class="lead">{{ $lead }}</p>@endif
  @if(!empty($side))
  <div class="two" style="grid-template-columns:minmax(0,1fr) 340px"><div class="prose">{!! $html !!}</div><aside class="box">{!! $side !!}</aside></div>
  @else
  <div class="prose">{!! $html !!}</div>
  @endif
  @if(!empty($source))<p class="note">Nội dung lấy từ {{ $source }}.</p>@endif
</div>
@endsection
