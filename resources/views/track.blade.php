@extends('layouts.app')
@section('content')
<div class="wrap">
  <div class="crumb"><a href="/">Trang chủ</a> › Tra cứu đơn hàng</div>
  <h1 class="h1">Tra cứu đơn hàng</h1>
  <p class="lead">Nhập mã đơn (dạng YS…) và số điện thoại đã dùng khi đặt hàng.</p>
  <form class="box" method="get" style="max-width:560px;display:grid;grid-template-columns:1fr 1fr auto;gap:10px;align-items:end">
    <div class="field" style="margin:0"><label for="t1">Mã đơn</label><input id="t1" name="ma" value="{{ $ma }}" required></div>
    <div class="field" style="margin:0"><label for="t2">Số điện thoại</label><input id="t2" name="sdt" value="{{ $sdt }}" inputmode="tel" required></div>
    <button class="btn btn-sea">Tra cứu</button>
  </form>
  @if($searched && !$order)
    <div class="empty" style="max-width:560px;margin-top:16px">Không tìm thấy đơn khớp mã và số điện thoại này. Kiểm tra lại, hoặc gọi hotline {{ \App\Support\Company::get('hotline_sales') }}.</div>
  @endif
  @if($order)
    @php $flow = ['new' => 'Mới', 'confirmed' => 'Đã xác nhận', 'shipping' => 'Đang giao', 'completed' => 'Hoàn thành']; $keys = array_keys($flow); $at = array_search($order->status, $keys); @endphp
    <div class="box" style="max-width:760px;margin-top:18px">
      <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap"><h2 class="h2" style="font-size:22px">Đơn {{ $order->code }}</h2><span class="note">Đặt lúc {{ $order->created_at->format('H:i d/m/Y') }}</span></div>
      @if(in_array($order->status, ['cancelled', 'returned']))<p><span class="pill" style="background:var(--red50);color:var(--red)">Đơn đã hủy</span></p>
      @else<div class="steps">@foreach($flow as $k => $t)<div class="{{ $at !== false && array_search($k, $keys) <= $at ? 'on' : '' }}">{{ $t }}</div>@endforeach</div>@endif
      @foreach($order->items as $it)<div style="display:flex;justify-content:space-between;gap:10px;padding:8px 0;border-bottom:1px solid var(--line)"><span>{{ $it->product_name }} <span class="note">· {{ $it->variant_name }} × {{ $it->qty }}</span></span><b>{{ number_format($it->line_total, 0, ',', '.') }}đ</b></div>@endforeach
      <div class="sum" style="margin-top:8px">@if($order->discount)<div><span>Giảm giá</span><b>−{{ number_format($order->discount, 0, ',', '.') }}đ</b></div>@endif<div class="tot"><span>Tổng tiền hàng</span><span>{{ number_format($order->total, 0, ',', '.') }}đ</span></div></div>
      <p class="note">Giao tới: {{ $order->ship_name }}, {{ $order->ship_address }}</p>
    </div>
  @endif
</div>
@endsection
