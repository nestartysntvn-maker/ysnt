<x-filament-panels::page>
@php $vnd = fn ($n) => number_format((int) $n, 0, ',', '.') . 'đ'; @endphp
<style>
.pos{display:grid;grid-template-columns:minmax(0,1fr) 400px;gap:20px;align-items:start}
.pos .panel{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:14px}
.dark .pos .panel{background:#18181b;border-color:#3f3f46}
.pos input,.pos select{width:100%;border:1px solid #d1d5db;border-radius:10px;padding:9px 11px;background:transparent}
.pos .chips{display:flex;gap:6px;flex-wrap:wrap;margin:10px 0}
.pos .chip{border:1px solid #d1d5db;border-radius:999px;padding:5px 12px;font-size:13px;background:transparent;cursor:pointer}
.pos .chip.on{background:#0f766e;border-color:#0f766e;color:#fff}
.pos .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px;max-height:calc(100vh - 280px);overflow:auto;padding-right:4px}
.pos .it{border:1px solid #e5e7eb;border-radius:12px;padding:8px;text-align:left;background:transparent;cursor:pointer;display:flex;flex-direction:column;gap:3px}
.pos .it:hover{border-color:#0f766e;box-shadow:0 0 0 1px #0f766e}
.pos .it img{width:100%;aspect-ratio:1;object-fit:cover;border-radius:8px;background:#f4e7c4}
.pos .it b{font-size:13px;line-height:1.25}.pos .it small{font-size:12px;opacity:.7}.pos .it .p{color:#b3121b;font-weight:700}
.pos .row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px;padding:8px 0;border-bottom:1px solid #f3f4f6}
.pos .qty{display:inline-flex;align-items:center;gap:6px}.pos .qty button{width:28px;height:28px;border:1px solid #d1d5db;border-radius:8px;background:transparent}
.pos .sum div{display:flex;justify-content:space-between;padding:3px 0}.pos .tot{font-size:22px;font-weight:700;border-top:1px solid #e5e7eb;margin-top:6px;padding-top:8px}
.pos .pay{display:grid;grid-template-columns:repeat(3,1fr);gap:6px;margin:8px 0}
.pos .pay button{border:1.5px solid #d1d5db;border-radius:10px;padding:8px;background:transparent;font-weight:600}
.pos .pay button.on{border-color:#0f766e;background:#f0fdfa;color:#0f766e}
.pos .go{width:100%;background:#b3121b;color:#fff;border:0;border-radius:12px;padding:14px;font-size:17px;font-weight:700;margin-top:10px;cursor:pointer}
.pos .go:disabled{opacity:.4;cursor:not-allowed}
.pos label{font-size:12px;font-weight:600;opacity:.75;display:block;margin:8px 0 3px}
.pos .hint{font-size:12px;margin-top:3px}.pos .ok{color:#0f766e}.pos .warn{color:#b45309}
@media (max-width:1100px){.pos{grid-template-columns:1fr}.pos .grid{max-height:none}}
</style>
<div class="pos">
  <div class="panel">
    <input type="search" wire:model.live.debounce.250ms="search" wire:keydown.enter="scan" placeholder="Tìm tên, SKU hoặc quét mã vạch rồi Enter" autofocus>
    <div class="chips">
      <button class="chip {{ $cat ? '' : 'on' }}" wire:click="$set('cat', null)">Tất cả</button>
      @foreach($cats as $c)<button class="chip {{ $cat === $c->id ? 'on' : '' }}" wire:click="$set('cat', {{ $c->id }})">{{ $c->name }}</button>@endforeach
    </div>
    <div class="grid">
      @forelse($items as $v)
        @php $img = $v->product->image ? (str_starts_with($v->product->image, '//') ? 'https:' . $v->product->image : $v->product->image) : null; @endphp
        <button class="it" wire:click="add({{ $v->id }})" wire:key="v{{ $v->id }}">
          @if($img)<img src="{{ $img }}" alt="" loading="lazy">@endif
          <b>{{ $v->product->name }}</b><small>{{ $v->name }}</small>
          <span class="p">{{ $vnd($v->list_price) }}</span>
          <small>{{ $v->sku }} · {{ $v->stock_qty === null ? 'Chưa theo dõi tồn' : 'Tồn ' . $v->stock_qty }}</small>
        </button>
      @empty
        <p style="opacity:.7">Không có sản phẩm khớp “{{ $search }}”.</p>
      @endforelse
    </div>
  </div>

  <div class="panel">
    <div style="display:flex;justify-content:space-between;align-items:center"><b style="font-size:16px">Bill đang bán</b>
      @if($lines->count())<button style="font-size:13px;text-decoration:underline;background:none;border:0;cursor:pointer" wire:click="clearCart">Xóa bill</button>@endif</div>
    @forelse($lines as $id => $l)
      <div class="row" wire:key="c{{ $id }}">
        <div><div style="font-weight:600;font-size:14px">{{ $l['v']->product->name }}</div><div style="font-size:12px;opacity:.7">{{ $l['v']->name }} · {{ $vnd($l['v']->list_price) }}</div>
          <div class="qty" style="margin-top:4px"><button wire:click="dec({{ $id }})">−</button><b>{{ $l['q'] }}</b><button wire:click="add({{ $id }})">+</button>
            <button style="border:0;width:auto;font-size:12px;text-decoration:underline" wire:click="remove({{ $id }})">Bỏ</button></div></div>
        <b>{{ $vnd($l['t']) }}</b>
      </div>
    @empty
      <p style="opacity:.7;padding:24px 0;text-align:center">Chạm sản phẩm bên trái để thêm vào bill.</p>
    @endforelse

    <label>SĐT khách (không bắt buộc)</label><input wire:model.blur="phone" inputmode="tel" placeholder="Để tích điểm, nhắc mua lại">
    @if($phone !== '')<label>Tên khách</label><input wire:model.blur="customerName" placeholder="Không bắt buộc">@endif
    <label>Mã HDV hoặc đại sứ</label><input wire:model.live.debounce.400ms="refCode" placeholder="Ví dụ HDVNAM01" style="text-transform:uppercase">
    @if($refCode !== '')
      <div class="hint {{ $amb ? 'ok' : 'warn' }}">
        @if($amb){{ $amb->name }} · {{ \App\Filament\Resources\Ambassadors\AmbassadorResource::TYPES[$amb->type] ?? $amb->type }}@if($t['rate'] !== null) · hoa hồng {{ rtrim(rtrim(number_format($t['rate'], 2, ',', ''), '0'), ',') }}%@else · loại này chưa chốt mức hoa hồng @endif
        @else Mã chưa có trong hệ thống. Vẫn bán được, mã được ghi lại để đối soát. @endif
      </div>
    @endif
    @if($this->isAdmin())
      <label>Giảm giá tay (chỉ Quản trị)</label>
      <div style="display:grid;grid-template-columns:120px 1fr;gap:6px"><input wire:model.live.debounce.400ms="manualDiscount" inputmode="numeric" placeholder="0"><input wire:model.blur="discountReason" placeholder="Lý do (bắt buộc nếu giảm)"></div>
    @endif

    <div class="sum" style="margin-top:12px">
      <div><span>Tạm tính</span><b>{{ $vnd($t['sub']) }}</b></div>
      @if($t['refDisc'])<div><span>Giảm {{ rtrim(rtrim(number_format($t['refPct'], 2, ',', ''), '0'), ',') }}% theo mã</span><b>−{{ $vnd($t['refDisc']) }}</b></div>@endif
      @if($t['manual'])<div><span>Giảm tay</span><b>−{{ $vnd($t['manual']) }}</b></div>@endif
      <div class="tot"><span>Khách trả</span><span>{{ $vnd($t['total']) }}</span></div>
    </div>
    <div class="pay">
      @foreach(['cash' => 'Tiền mặt', 'bank' => 'Chuyển khoản', 'card' => 'Quẹt thẻ'] as $k => $lbl)<button class="{{ $pay === $k ? 'on' : '' }}" wire:click="$set('pay', '{{ $k }}')">{{ $lbl }}</button>@endforeach
    </div>
    @if($pay === 'cash')
      <label>Tiền khách đưa</label><input wire:model.live.debounce.300ms="cashGiven" inputmode="numeric" placeholder="{{ number_format($t['total'], 0, ',', '.') }}">
      @if($t['cash'] > 0)<div class="hint {{ $t['change'] >= 0 ? 'ok' : 'warn' }}" style="font-size:15px;font-weight:700">{{ $t['change'] >= 0 ? 'Thối lại ' . $vnd($t['change']) : 'Còn thiếu ' . $vnd(-$t['change']) }}</div>@endif
    @endif
    <button class="go" wire:click="checkout" wire:loading.attr="disabled" @disabled(!$lines->count())>Thanh toán {{ $vnd($t['total']) }}</button>
    @if($last)
      <p class="hint ok" style="margin-top:10px">Đơn vừa bán: <b>{{ $last->code }}</b> · {{ $vnd($last->total) }} · <a href="{{ route('pos.bill', $last) }}" target="_blank" style="text-decoration:underline">In lại bill</a></p>
    @endif
  </div>
</div>
<script>
  window.addEventListener('pos-print', e => { const u = e.detail?.url ?? (e.detail && e.detail[0]?.url); if (u) window.open(u, 'bill', 'width=380,height=640'); });
</script>
</x-filament-panels::page>
