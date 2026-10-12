<!doctype html>
<html lang="vi"><head><meta charset="utf-8"><title>Bill {{ $o->code }}</title>
<style>
@page{size:80mm auto;margin:3mm}
body{font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#000;width:74mm;margin:0 auto}
h1{font-size:15px;margin:0;text-align:center}.c{text-align:center}.m{margin:2px 0}
table{width:100%;border-collapse:collapse}td{padding:2px 0;vertical-align:top}.r{text-align:right}
.line{border-top:1px dashed #000;margin:6px 0}.big{font-size:15px;font-weight:bold}
@media screen{body{margin:12px auto;padding:8px;box-shadow:0 0 0 1px #ddd}.noprint{margin-top:10px;text-align:center}}
@media print{.noprint{display:none}}
</style></head>
<body onload="window.print()">
@php $vnd = fn ($n) => number_format((int) $n, 0, ',', '.'); @endphp
@php $C = \App\Support\Company::class; @endphp
@if($C::logoUrl())<p class="c m"><img src="{{ $C::logoUrl() }}" alt="" style="max-height:48px;filter:grayscale(1)"></p>@endif
<h1>{{ mb_strtoupper($C::get('brand_name')) }}</h1>
<p class="c m">{{ $C::get('company_name') }}</p>
<p class="c m">{{ $C::get('showroom_address') }} · Hotline {{ $C::get('hotline_sales') }}</p>
<div class="line"></div>
<p class="m"><b>HÓA ĐƠN BÁN LẺ</b> · {{ $o->code }}</p>
<p class="m">{{ $o->created_at->format('H:i d/m/Y') }} · NV: {{ $staff }}</p>
@if($o->ship_phone)<p class="m">Khách: {{ $o->ship_name }} · {{ $o->ship_phone }}</p>@endif
<div class="line"></div>
<table>
@foreach($o->items as $i)
<tr><td colspan="2">{{ $i->product_name }} · {{ $i->variant_name }}</td></tr>
<tr><td>{{ $i->qty }} × {{ $vnd($i->unit_price) }}</td><td class="r">{{ $vnd($i->line_total) }}</td></tr>
@endforeach
</table>
<div class="line"></div>
<table>
<tr><td>Tạm tính</td><td class="r">{{ $vnd($o->subtotal) }}</td></tr>
@if($o->discount)<tr><td>Giảm giá</td><td class="r">-{{ $vnd($o->discount) }}</td></tr>@endif
<tr class="big"><td>TỔNG</td><td class="r">{{ $vnd($o->total) }}đ</td></tr>
<tr><td>Thanh toán</td><td class="r">{{ ['cash' => 'Tiền mặt', 'bank' => 'Chuyển khoản', 'card' => 'Quẹt thẻ'][$o->payment_method] ?? $o->payment_method }}</td></tr>
@if($o->ref_code)<tr><td>Mã giới thiệu</td><td class="r">{{ $o->ref_code }}</td></tr>@endif
</table>
<div class="line"></div>
<p class="c m">Cảm ơn quý khách. Hẹn gặp lại!</p>
<p class="c m">Tra cứu đơn: nhaviettesting.go365.vn/tra-cuu-don</p>
<div class="noprint"><button onclick="window.print()">In lại</button></div>
</body></html>
