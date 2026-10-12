@php $C = \App\Support\Company::class; @endphp
<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.6;color:#1f2937;max-width:560px">
  <p style="font-size:18px;font-weight:bold;margin:0 0 12px">{{ $C::get('brand_name') }}</p>
  @foreach($lines as $l)<p style="margin:0 0 10px">{!! nl2br(e($l)) !!}</p>@endforeach
  @if(! empty($rows))<table style="border-collapse:collapse;margin:8px 0 14px">@foreach($rows as $k => $v)<tr><td style="padding:4px 14px 4px 0;color:#6b7280">{{ $k }}</td><td style="padding:4px 0;font-weight:bold">{{ $v }}</td></tr>@endforeach</table>@endif
  @if(! empty($button))<p style="margin:16px 0"><a href="{{ $button[1] }}" style="background:#0b4f55;color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px;display:inline-block">{{ $button[0] }}</a></p>@endif
  <p style="color:#6b7280;font-size:12px;margin-top:18px">{{ $C::get('company_name') }} · {{ $C::get('hq_address') }} · Hotline {{ $C::get('hotline_sales') }}</p>
</div>
