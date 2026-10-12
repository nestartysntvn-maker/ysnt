<x-filament-panels::page>
<style>
.cv{display:grid;grid-template-columns:minmax(0,1fr) 300px;gap:16px;align-items:start}@media(max-width:960px){.cv{grid-template-columns:1fr}}
.cv-card{background:#fff;border:1px solid #e5e7eb;border-radius:14px}.dark .cv-card{background:#18181b;border-color:#3f3f46}
.cv-thread{padding:16px;display:flex;flex-direction:column;gap:10px;max-height:62vh;overflow:auto}
.cv-m{max-width:78%;padding:9px 12px;border-radius:14px;font-size:14px;line-height:1.5;white-space:pre-wrap;word-break:break-word}
.cv-in{align-self:flex-start;background:#f3f4f6}.dark .cv-in{background:#27272a}.cv-out{align-self:flex-end;background:#ccfbf1}.dark .cv-out{background:#134e4a}
.cv-ai{background:#ede9fe}.dark .cv-ai{background:#3b0764}.cv-sys{align-self:center;background:transparent;font-size:12px;opacity:.7;text-align:center}
.cv-meta{font-size:11px;opacity:.65;margin-top:4px}.cv-card .cards{display:grid;gap:6px;margin-top:8px}.cv-card .card{border:1px solid #d1d5db;border-radius:10px;padding:6px 8px;font-size:12px}
.cv-box{border-top:1px solid #e5e7eb;padding:12px}.dark .cv-box{border-color:#3f3f46}.cv textarea,.cv input{width:100%;border:1px solid #d1d5db;border-radius:10px;padding:9px 11px;background:transparent;font-size:14px}
.cv textarea{min-height:90px}.cv .row{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;align-items:center}.cv button{border-radius:10px;padding:8px 14px;font-weight:600;border:0;cursor:pointer;font-size:13px}
.cv .p{background:#0f766e;color:#fff}.cv .g{background:#f3f4f6;color:#111}.dark .cv .g{background:#3f3f46;color:#fff}.cv .on{outline:2px solid #0f766e}
.cv-side{padding:16px;display:grid;gap:12px;font-size:14px}.cv-side h4{font-size:12px;font-weight:600;opacity:.6;margin:0 0 4px}.cv .warn{background:#fef2f2;color:#991b1b;border-radius:10px;padding:8px 10px;font-size:13px}
.cv .att a{text-decoration:underline;font-size:12px}
</style>
@php $c = $this->conv(); $st = \App\Models\ConversationMessage::STATUS; $vnd = fn ($n) => number_format((int) $n, 0, ',', '.') . 'đ'; @endphp
<div class="cv" wire:poll.10s>
  <div class="cv-card">
    <div class="cv-thread" id="cv-thread">
      @forelse($c->messages as $m)
        @php $cls = $m->sender === 'system' ? 'cv-sys' : ($m->direction === 'in' ? 'cv-in' : ($m->sender === 'ai' ? 'cv-out cv-ai' : 'cv-out')); @endphp
        <div class="cv-m {{ $cls }}">{{ $m->body }}
          @php $cards = collect($m->attachments ?? []); @endphp
          @if($cards->where('type', 'product')->isNotEmpty())<div class="cards">@foreach($cards->where('type', 'product') as $p)<div class="card">{{ $p['name'] }} {{ $p['variant'] ? '– ' . $p['variant'] : '' }} · {{ $vnd($p['price']) }}</div>@endforeach</div>@endif
          @foreach($cards->where('type', '!=', 'product') as $a)<div class="att"><a href="{{ $a['url'] }}" target="_blank" rel="noopener">Tệp {{ $a['type'] }}</a></div>@endforeach
          <div class="cv-meta">{{ $m->created_at->format('H:i d/m') }} · {{ $m->direction === 'in' ? 'Khách' : ($m->sender === 'ai' ? 'AI' : ($m->sender === 'system' ? 'Tự động' : ($m->user?->name ?? 'Nhân viên'))) }}
            @if($m->direction === 'out') · {{ $st[$m->status] ?? $m->status }}@endif @if($m->error && $m->status !== 'sent') · {{ $m->error }}@endif</div>
        </div>
      @empty
        <p class="cv-sys cv-m">Chưa có tin nào.</p>
      @endforelse
    </div>
    <div class="cv-box">
      <textarea wire:model="reply" placeholder="Nhập câu trả lời cho khách…" aria-label="Câu trả lời"></textarea>
      <div class="row">
        <button class="p" wire:click="send" wire:loading.attr="disabled">Gửi</button>
        <button class="g" wire:click="suggest" wire:loading.attr="disabled">AI gợi ý trả lời</button>
        <span wire:loading class="cv-meta">Đang xử lý…</span>
      </div>
    </div>
  </div>
  <aside class="cv-card cv-side">
    @if($c->needs_human)<div class="warn">Khách cần nhân viên trả lời. AI tạm dừng ở hội thoại này. <button class="g" wire:click="handBack" style="margin-top:6px">Đã xử lý, cho AI trả lời tiếp</button></div>@endif
    <div><h4>Khách hàng</h4>
      @if($c->customer)<b>{{ $c->customer->name }}</b><br>{{ $c->customer->phone }} · {{ $c->customer->orders()->count() }} đơn
      @else<span>Chưa gắn khách hàng. Nhập số điện thoại khách cung cấp để gộp vào hồ sơ chung.</span>
        <div class="row"><input wire:model="phone" placeholder="09xxxxxxxx" aria-label="Số điện thoại"><button class="g" wire:click="link">Gắn</button></div>
      @endif
    </div>
    <div><h4>Nhóm</h4><div class="row" style="margin-top:0">@foreach(\App\Models\Conversation::TAGS as $k => $l)<button class="g {{ $c->tag === $k ? 'on' : '' }}" wire:click="setTag('{{ $c->tag === $k ? '' : $k }}')">{{ $l }}</button>@endforeach</div></div>
    <div><h4>AI tự trả lời</h4><button class="g" wire:click="toggleAi">{{ $c->ai_enabled ? 'Đang bật · Tắt cho hội thoại này' : 'Đang tắt · Bật lại' }}</button></div>
    <div><h4>Trạng thái</h4><button class="g" wire:click="close">{{ $c->status === 'open' ? 'Đánh dấu đã xong' : 'Mở lại' }}</button></div>
    <div><h4>Phụ trách</h4>{{ $c->assignee?->name ?? 'Chưa có' }}</div>
  </aside>
</div>
<script>document.addEventListener('livewire:navigated',()=>{const t=document.getElementById('cv-thread');if(t)t.scrollTop=t.scrollHeight});(()=>{const t=document.getElementById('cv-thread');if(t)t.scrollTop=t.scrollHeight})();</script>
</x-filament-panels::page>
