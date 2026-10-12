<x-filament-panels::page>
<style>
.sf{max-width:980px;display:grid;gap:16px}.sf-card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px 20px}.dark .sf-card{background:#18181b;border-color:#3f3f46}
.sf h3{font-weight:700;font-size:15px;margin:0}.sf .desc{font-size:13px;opacity:.7;margin:4px 0 0}
.sf-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:4px 18px;margin-top:6px}@media(max-width:720px){.sf-grid{grid-template-columns:1fr}}
.sf .wide{grid-column:1/-1}.sf label{display:block;font-size:12px;font-weight:600;opacity:.75;margin:12px 0 4px}
.sf input[type=text],.sf input[type=password],.sf select,.sf textarea{width:100%;border:1px solid #d1d5db;border-radius:10px;padding:9px 11px;background:transparent;font-size:14px}
.dark .sf input,.dark .sf select,.dark .sf textarea{border-color:#52525b}.sf select option{color:#111}
.sf textarea{min-height:120px;font-family:inherit;line-height:1.5}.sf .hint{font-size:12px;opacity:.65;margin-top:3px}
.sf .tg{display:flex;gap:10px;align-items:flex-start;margin-top:14px;cursor:pointer}.sf .tg input{width:18px;height:18px;margin-top:2px;accent-color:#0f766e}.sf .tg b{font-size:14px;font-weight:600}
.sf .row{display:flex;gap:10px;flex-wrap:wrap;align-items:center}.sf button{border-radius:10px;padding:10px 16px;font-weight:600;border:0;cursor:pointer}
.sf .p{background:#0f766e;color:#fff}.sf .g{background:#f3f4f6;color:#111}.dark .sf .g{background:#3f3f46;color:#fff}
.sf .mode{padding:12px 14px;border-radius:10px;font-size:14px;line-height:1.5}.sf .info{background:#eff6ff;color:#1e40af}.sf .ok{background:#ecfdf5;color:#065f46}.sf .warn{background:#fffbeb;color:#92400e}
.sf .ro{display:flex;gap:6px}.sf .ro input{opacity:.85}.sf .have{font-size:12px;color:#047857;font-weight:600}.sf .clr{display:flex;gap:8px;align-items:center}.sf .clr input[type=color]{width:44px;height:38px;border:0;background:none;padding:0}
.sf .bar{position:sticky;bottom:0;padding:12px 0;background:linear-gradient(transparent,var(--gray-50,#f9fafb) 30%)}.dark .sf .bar{background:linear-gradient(transparent,#09090b 30%)}
</style>
<div class="sf">
  @if($b = $this->banner())<div class="mode {{ $b['tone'] }}">{!! $b['html'] !!}</div>@endif
  @foreach($this->sections() as $title => $sec)
  <section class="sf-card">
    <h3>{{ $title }}</h3>@if(! empty($sec['desc']))<p class="desc">{!! $sec['desc'] !!}</p>@endif
    <div class="sf-grid">
    @foreach($sec['fields'] as $k => $s)
      @php $t = $s['type'] ?? 'text'; $wide = ($s['wide'] ?? false) || in_array($t, ['textarea', 'info'], true); @endphp
      <div class="{{ $wide ? 'wide' : '' }}">
      @if($t === 'toggle')
        <label class="tg"><input type="checkbox" wire:model="f.{{ $k }}"><span><b>{{ $s['label'] }}</b>@if(! empty($s['hint']))<span class="hint" style="display:block">{!! $s['hint'] !!}</span>@endif</span></label>
      @else
        <label for="f-{{ $k }}">{{ $s['label'] }} @if(in_array($t, ['secret', 'file', 'image'], true) && $this->has($k))<span class="have">· đã có</span>@endif</label>
        @switch($t)
          @case('secret')<input id="f-{{ $k }}" type="password" wire:model="f.{{ $k }}" autocomplete="new-password" placeholder="{{ $this->has($k) ? 'Đã lưu (mã hóa). Để trống nếu không đổi' : 'Chưa có' }}">@break
          @case('textarea')<textarea id="f-{{ $k }}" wire:model="f.{{ $k }}" placeholder="{{ $s['placeholder'] ?? '' }}"></textarea>@break
          @case('select')<select id="f-{{ $k }}" wire:model="f.{{ $k }}">@foreach($s['options'] as $ov => $ol)<option value="{{ $ov }}">{{ $ol }}</option>@endforeach</select>@break
          @case('color')<div class="clr"><input type="color" value="{{ $f[$k] ?: '#0b4f55' }}" aria-label="{{ $s['label'] }}" oninput="const t=this.nextElementSibling;t.value=this.value;t.dispatchEvent(new Event('input'))"><input id="f-{{ $k }}" type="text" wire:model="f.{{ $k }}" placeholder="Để trống: giữ màu hiện tại"></div>@break
          @case('info')<div class="ro"><input id="f-{{ $k }}" type="text" readonly value="{{ $s['value'] ?? '' }}" onclick="this.select()"><button type="button" class="g" onclick="navigator.clipboard.writeText(this.previousElementSibling.value);this.textContent='Đã chép'">Chép</button></div>@break
          @case('file')
          @case('image')
            <input id="f-{{ $k }}" type="file" wire:model="up.{{ $k }}" accept="{{ $t === 'image' ? 'image/png,image/jpeg,image/webp' : '.pdf,image/png,image/jpeg' }}">
            @if($this->has($k))
              @if($t === 'image')<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url(\App\Models\AppSetting::get($k)) }}" alt="" style="max-height:56px;margin-top:8px">
              @else<a class="hint" style="display:inline-block;margin-top:6px;text-decoration:underline" href="/quan-tri/tai-file/{{ $k }}" target="_blank">Xem tệp đã lưu</a>@endif
            @endif
            @error("up.$k")<p class="hint" style="color:#b91c1c">{{ $message }}</p>@enderror
          @break
          @default<input id="f-{{ $k }}" type="text" wire:model="f.{{ $k }}" placeholder="{{ $s['placeholder'] ?? '' }}">
        @endswitch
        @error("f.$k")<p class="hint" style="color:#b91c1c">{{ $message }}</p>@enderror
        @if(! empty($s['hint']))<p class="hint">{!! $s['hint'] !!}</p>@endif
      @endif
      </div>
    @endforeach
    </div>
  </section>
  @endforeach
  <div class="bar"><div class="row">
    <button class="p" wire:click="save" wire:loading.attr="disabled">Lưu</button>
    @foreach($this->buttons() as $m => $lbl)<button class="g" wire:click="{{ $m }}" wire:loading.attr="disabled">{{ $lbl }}</button>@endforeach
    <span wire:loading class="hint">Đang xử lý…</span>
  </div>
  @if($status)<div class="mode info" style="margin-top:10px">{{ $status }}</div>@endif
  </div>
</div>
</x-filament-panels::page>
