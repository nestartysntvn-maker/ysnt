@php $vnd = fn ($n) => number_format((int) $n, 0, ',', '.') . 'đ'; $C = \App\Support\Company::class; @endphp
<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#1f2937;max-width:560px">
  <p style="font-size:18px;font-weight:bold;margin:0 0 4px">{{ $C::get('brand_name') }}</p>
  @if($internal)
    <p>Có đơn mới <b>{{ $o->code }}</b> từ kênh {{ $o->channel }}. Khách: {{ $o->ship_name }} · {{ $o->ship_phone }}.</p>
  @else
    <p>Chào {{ $o->ship_name ?: 'anh/chị' }}, {{ $C::get('brand_name') }} đã nhận đơn <b>{{ $o->code }}</b>. Nhân viên sẽ gọi xác nhận và báo phí giao hàng trong giờ làm việc.</p>
  @endif
  <table style="width:100%;border-collapse:collapse;margin:12px 0">
    @foreach($o->items as $i)<tr><td style="padding:6px 0;border-bottom:1px solid #e5e7eb">{{ $i->product_name }} · {{ $i->variant_name }} × {{ $i->qty }}</td><td style="padding:6px 0;border-bottom:1px solid #e5e7eb;text-align:right">{{ $vnd($i->line_total) }}</td></tr>@endforeach
    @if($o->discount)<tr><td style="padding:6px 0">Giảm giá</td><td style="text-align:right">−{{ $vnd($o->discount) }}</td></tr>@endif
    <tr><td style="padding:6px 0;font-weight:bold">Tổng tiền hàng</td><td style="text-align:right;font-weight:bold">{{ $vnd($o->total) }}</td></tr>
  </table>
  @unless($internal)<p><a href="{{ url('/tra-cuu-don?ma=' . $o->code . '&sdt=' . $o->ship_phone) }}">Xem trạng thái đơn</a></p>@endunless
  <p style="color:#6b7280;font-size:12px">{{ $C::get('company_name') }} · Hotline {{ $C::get('hotline_sales') }} · {{ $C::get('email_public') }}</p>
</div>
