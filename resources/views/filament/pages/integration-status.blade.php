<x-filament-panels::page>
<style>
.is{display:grid;gap:16px;max-width:1100px}.is-card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;overflow:hidden}.dark .is-card{background:#18181b;border-color:#3f3f46}
.is h3{font-weight:700;font-size:15px;padding:14px 18px;margin:0;border-bottom:1px solid #e5e7eb}.dark .is h3{border-color:#3f3f46}
.is-row{display:grid;grid-template-columns:minmax(180px,1.4fr) 120px minmax(110px,.9fr) minmax(200px,1.6fr) 70px;gap:12px;align-items:center;padding:10px 18px;border-bottom:1px solid #f3f4f6;font-size:14px}
.dark .is-row{border-color:#27272a}.is-row:last-child{border-bottom:0}.is-head{font-size:12px;font-weight:600;opacity:.6;padding-top:8px;padding-bottom:8px}
.is .b{display:inline-block;border-radius:999px;padding:3px 10px;font-size:12px;font-weight:600}.is .done{background:#dcfce7;color:#166534}.is .partial{background:#fef3c7;color:#92400e}.is .missing{background:#f3f4f6;color:#4b5563}
.dark .is .missing{background:#3f3f46;color:#e4e4e7}.is input{width:100%;border:1px solid #d1d5db;border-radius:8px;padding:6px 9px;background:transparent;font-size:13px}.dark .is input{border-color:#52525b}
.is a.o{color:#0f766e;font-weight:600}.is .live{font-size:13px;opacity:.8}.is .sum{font-size:14px;opacity:.8}
.is button{border-radius:10px;padding:10px 16px;font-weight:600;border:0;cursor:pointer;background:#0f766e;color:#fff}
@media(max-width:820px){.is-row{grid-template-columns:1fr auto;gap:6px 10px}.is-head{display:none}.is-row .live,.is-row .nt{grid-column:1/-1}.is-row .op{grid-column:2;grid-row:1}.is-row .stc{grid-column:1/-1}}
</style>
@php $groups = static::groups(); $all = collect($groups)->flatten(1); $lbl = ['done' => 'Đã có', 'partial' => 'Có một phần', 'missing' => 'Chưa có']; @endphp
<div class="is">
  <p class="sum">Theo file "Thông tin cần thiết": <b>{{ $all->where('status', 'done')->count() }}</b> mục đã có, <b>{{ $all->where('status', 'partial')->count() }}</b> có một phần, <b>{{ $all->where('status', 'missing')->count() }}</b> chưa có. Mục nào chưa có thì hệ thống chạy thử: ghi nhật ký, không gửi ra ngoài.</p>
  @foreach($groups as $title => $rows)
  <section class="is-card"><h3>{{ $title }}</h3>
    <div class="is-row is-head"><span>Mục</span><span>Trạng thái</span><span>Đang chạy</span><span>Ghi chú</span><span></span></div>
    @foreach($rows as $r)
    <div class="is-row">
      <span>{{ $r['label'] }}</span>
      <span class="stc"><span class="b {{ $r['status'] }}">{{ $lbl[$r['status']] }}</span></span>
      <span class="live">{{ $r['live'] ?: '—' }}</span>
      <span class="nt"><input wire:model="notes.{{ $r['code'] }}" placeholder="{{ $r['status'] === 'done' ? '' : 'Chưa có: ghi ai lo, khi nào có' }}" aria-label="Ghi chú {{ $r['label'] }}"></span>
      <span class="op"><a class="o" href="{{ $r['url'] }}">Mở</a></span>
    </div>
    @endforeach
  </section>
  @endforeach
  <div><button wire:click="saveNotes">Lưu ghi chú</button></div>
</div>
</x-filament-panels::page>
