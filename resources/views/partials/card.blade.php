@php
  $vs = $p->variants; $ok = $vs->where('is_available', true);
  $min = ($ok->count() ? $ok : $vs)->min('list_price');
  $img = $p->image ? (str_starts_with($p->image, '//') ? 'https:' . $p->image : $p->image) : null;
@endphp
<a class="card" href="/san-pham/{{ $p->slug }}">
  <div class="ph">@if($img)<img loading="lazy" src="{{ $img }}" alt="{{ $p->name }}">@endif
    @if($p->brand?->name === 'Nest Art')<span class="tag">Nest Art</span>@endif
    @if($ok->count() === 0)<span class="out">Tạm hết hàng</span>@endif</div>
  <div class="nm">{{ $p->name }}</div>
  <div class="pr">@if($vs->count() > 1)từ @endif{{ number_format($min, 0, ',', '.') }}đ</div>
  <div class="vc">@if($vs->count() > 1){{ $vs->count() }} lựa chọn @elseif($vs->first() && $vs->first()->name !== 'Tiêu chuẩn'){{ $vs->first()->name }}@endif</div>
</a>
