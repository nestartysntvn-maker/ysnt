<x-filament-panels::page>
<style>
.ki{display:grid;gap:16px;max-width:1100px}
.ki-card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px 20px}.dark .ki-card{background:#18181b;border-color:#3f3f46}
.ki h3{font-weight:700;font-size:15px;margin:0 0 10px}.ki .muted{opacity:.7;font-size:13px}.ki .lead{font-size:14px;opacity:.85;margin:0 0 14px}
.ki-steps{display:flex;gap:6px;flex-wrap:wrap;margin:0;padding:0;list-style:none;counter-reset:s}
.ki-steps li{counter-increment:s;display:flex;align-items:center;gap:8px;padding:5px 12px 5px 5px;border-radius:999px;background:rgba(127,127,127,.12);font-size:13px;font-weight:600;opacity:.7}
.ki-steps li::before{content:counter(s);width:22px;height:22px;border-radius:50%;display:grid;place-items:center;border:1px solid rgba(127,127,127,.45);font-size:12px}
.ki-steps li.on{opacity:1;background:rgba(15,118,110,.14);color:#0f766e}.ki-steps li.on::before{background:#0f766e;border-color:#0f766e;color:#fff}
.ki-steps li.done{opacity:1;color:#0f766e}.ki-steps li.done::before{content:"✓";border-color:#0f766e}
.dark .ki-steps li.on,.dark .ki-steps li.done{color:#5eead4}
.ki-drop{position:relative;display:grid;gap:4px;justify-items:center;text-align:center;border:2px dashed rgba(127,127,127,.45);border-radius:14px;padding:30px 16px;cursor:pointer}
.ki-drop:hover,.ki-drop:focus-within{border-color:#0f766e;background:rgba(15,118,110,.06)}.ki-drop b{font-size:16px}
.ki-drop input{position:absolute;inset:0;width:100%;height:100%;opacity:0;cursor:pointer}
.ki textarea,.ki select{width:100%;border:1px solid #d1d5db;border-radius:10px;padding:9px 11px;background:transparent;font-size:14px;font-family:inherit}
.dark .ki textarea,.dark .ki select{border-color:#52525b}.ki select option{color:#111}.ki textarea{resize:vertical}
.ki-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-top:14px}.ki-actions .sp{flex:1}
.ki .p,.ki .g{display:inline-flex;align-items:center;gap:6px;border-radius:10px;padding:9px 16px;font-weight:600;font-size:14px;border:0;cursor:pointer;text-decoration:none}
.ki .p{background:#0f766e;color:#fff}.ki .g{background:#f3f4f6;color:#111}.dark .ki .g{background:#3f3f46;color:#fff}
.ki button:disabled{opacity:.45;cursor:not-allowed}
.ki-err{color:#b91c1c;font-size:13px;margin:10px 0 0}.dark .ki-err{color:#fca5a5}.ki-busy{color:#0f766e;font-weight:600;font-size:13px;margin-top:10px}
.ki-wrap{overflow-x:auto}.ki-tbl{width:100%;border-collapse:collapse;font-size:14px}
.ki-tbl th{text-align:left;font-size:12px;font-weight:600;opacity:.7;padding:9px 10px;border-bottom:1px solid rgba(127,127,127,.25);white-space:nowrap}
.ki-tbl td{padding:9px 10px;border-bottom:1px solid rgba(127,127,127,.15);vertical-align:top}.ki-tbl tr:last-child td{border-bottom:0}
.ki-tbl .num{text-align:right;white-space:nowrap}.ki .sub{display:block;font-size:12px;opacity:.7}.ki .nw{white-space:nowrap}
.ki .fix{display:block;font-size:12px;color:#b45309}.dark .ki .fix{color:#fbbf24}
.ki-pill{display:inline-block;border-radius:999px;padding:2px 9px;font-size:12px;font-weight:600;white-space:nowrap}
.ki-pill.ok{background:rgba(15,118,110,.14);color:#0f766e}.ki-pill.warn{background:rgba(217,119,6,.15);color:#b45309}.ki-pill.bad{background:rgba(220,38,38,.12);color:#b91c1c}
.dark .ki-pill.ok{color:#5eead4}.dark .ki-pill.warn{color:#fbbf24}.dark .ki-pill.bad{color:#fca5a5}
.ki-chips{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 14px}
.ki-chip{border:1px solid rgba(127,127,127,.35);border-radius:999px;padding:6px 12px;font-size:13px;font-weight:600;background:transparent;cursor:pointer}
.ki-chip b{margin-left:4px;font-variant-numeric:tabular-nums}.ki-chip.on{background:#0f766e;border-color:#0f766e;color:#fff}
.ki-opts{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:0 0 14px}.ki-opts label{display:block;font-size:12px;font-weight:600;opacity:.75;margin-bottom:4px}
.ki-scroll{max-height:55vh;overflow:auto;border:1px solid rgba(127,127,127,.25);border-radius:12px}
.ki-scroll thead th{position:sticky;top:0;z-index:1;background:#fff}.dark .ki-scroll thead th{background:#18181b}
.ki-ex{max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;opacity:.75;font-size:13px}
.ki-done{text-align:center;padding:20px 8px}.ki-done h2{font-size:22px;font-weight:700;margin:0 0 8px}.ki-done .ki-actions{justify-content:center}
.ki-tip ul{margin:0;padding-left:18px;font-size:14px;opacity:.85}.ki-tip li{margin:4px 0;list-style:disc}
@media(max-width:820px){.ki-opts{grid-template-columns:1fr}}
</style>
@php $steps = ['Chọn file', 'Ghép cột', 'Kiểm tra', 'Xong']; @endphp
<div class="ki">
  <ol class="ki-steps" aria-label="Các bước">
    @foreach($steps as $i => $t)<li class="{{ $i + 1 === $step ? 'on' : ($i + 1 < $step ? 'done' : '') }}" @if($i + 1 === $step) aria-current="step" @endif>{{ $t }}</li>@endforeach
  </ol>

  @if($step === 1)
    <section class="ki-card">
      <label class="ki-drop">
        <b>Chọn file Excel hoặc CSV</b>
        <span class="muted">Kéo thả file vào đây hoặc bấm để chọn file .xlsx, .xls, .csv (tối đa 2MB)</span>
        <input type="file" wire:model="upload" accept=".xlsx,.xls,.csv" aria-label="Chọn file danh sách khách">
      </label>
      <div wire:loading wire:target="upload" class="ki-busy">Đang đọc file…</div>
      @error('upload')<p class="ki-err" role="alert">{{ $message }}</p>@enderror
      @if($err)<p class="ki-err" role="alert">{{ $err }}</p>@endif
      <p class="muted" style="text-align:center;margin:18px 0 8px">hoặc chép các ô trong Excel, Google Sheets (gồm cả dòng tiêu đề) rồi dán vào đây</p>
      <textarea wire:model="pasted" rows="5" placeholder="Dán danh sách vào đây" aria-label="Dán danh sách khách"></textarea>
      <div class="ki-actions">
        <button type="button" class="g" wire:click="downloadTemplate">Tải file mẫu</button><span class="sp"></span>
        <button type="button" class="p" wire:click="usePaste" wire:loading.attr="disabled" wire:target="usePaste">Dùng dữ liệu đã dán</button>
      </div>
    </section>
    <section class="ki-card ki-tip">
      <h3>File cần có gì</h3>
      <ul>
        <li>Bắt buộc có cột <b>Họ tên</b> và <b>Số điện thoại</b>. Tên cột viết kiểu nào cũng được (SĐT, Điện thoại, Tên khách…), hệ thống tự nhận. File có dòng tiêu đề lớn ở trên cùng cũng được.</li>
        <li>Nếu có thì thêm: Email, Địa chỉ, Ngày sinh, Nhóm khách, Nguồn, Ghi chú.</li>
        <li>Số điện thoại bị Excel làm mất số 0, ghi dạng +84, hoặc còn đầu số cũ 11 số (0168…, 0122…) đều được tự sửa. Chỉ nhận số di động; số máy bàn sẽ nằm trong danh sách lỗi.</li>
        <li>Khách trùng số điện thoại không bị nhập hai lần. Chưa có gì được lưu cho đến khi bấm Nhập ở bước 3.</li>
      </ul>
    </section>

  @elseif($step === 2)
    @php $m = $this->cleanMap(); $first = $this->sample(); $ok = $m['name'] >= 0 && $m['phone'] >= 0; @endphp
    <section class="ki-card">
      <p class="lead"><b>{{ $fileName }}</b>@if($sheet) (trang tính “{{ $sheet }}”)@endif: {{ number_format($total, 0, ',', '.') }} dòng khách, {{ count($headers) }} cột{{ $headRow === 0 ? '. File không có dòng tiêu đề nên hệ thống đoán cột theo dữ liệu, xem lại trước khi đi tiếp.' : ($headRow > 1 ? ', tiêu đề ở dòng ' . $headRow : '') . '. Hệ thống đã tự ghép theo tên cột, sửa lại nếu chưa đúng.' }}</p>
      <div class="ki-wrap"><table class="ki-tbl">
        <thead><tr><th>Thông tin khách</th><th>Lấy từ cột trong file</th><th>Ví dụ ở dòng {{ $this->firstLine() }}</th></tr></thead>
        <tbody>
        @foreach(\App\Customers\CustomerImport::FIELDS as $f => $label)
          <tr>
            <td class="nw"><b>{{ $label }}</b> @if(in_array($f, ['name', 'phone'], true))<span class="ki-pill bad">bắt buộc</span>@endif</td>
            <td style="min-width:220px"><select wire:model.live="map.{{ $f }}" aria-label="Cột cho {{ $label }}">
              <option value="-1">Không dùng</option>
              @foreach($headers as $i => $h)<option value="{{ $i }}">{{ $h }}</option>@endforeach
            </select></td>
            <td><div class="ki-ex">{{ $m[$f] >= 0 ? ($first[$m[$f]] ?? '') : '' }}</div></td>
          </tr>
        @endforeach
        </tbody>
      </table></div>
      @if(! $ok)<p class="ki-err" role="alert">Chọn cột cho Họ tên và Số điện thoại để đi tiếp.</p>@endif
      @if($err)<p class="ki-err" role="alert">{{ $err }}</p>@endif
      <div class="ki-actions">
        <button type="button" class="g" wire:click="toStep(1)">Chọn file khác</button><span class="sp"></span>
        <button type="button" class="p" wire:click="toStep(3)" @disabled(! $ok)>Kiểm tra dữ liệu</button>
      </div>
    </section>

  @elseif($step === 3)
    @php $n = $this->counts(); $rows = $this->visible(); $chips = ['all' => 'Tất cả', 'new' => 'Thêm mới', 'dup' => 'Trùng số điện thoại', 'err' => 'Lỗi', 'fix' => 'Đã tự sửa']; @endphp
    <section class="ki-card">
      <p class="lead">Chưa có gì được lưu. Xem kết quả kiểm tra, chọn cách xử lý rồi bấm Nhập.</p>
      <div class="ki-chips" role="group" aria-label="Lọc kết quả">
        @foreach($chips as $k => $t)@if($k !== 'fix' || $n['fix'])<button type="button" class="ki-chip {{ $filter === $k ? 'on' : '' }}" aria-pressed="{{ $filter === $k ? 'true' : 'false' }}" wire:click="setFilter('{{ $k }}')">{{ $t }} <b>{{ number_format($n[$k], 0, ',', '.') }}</b></button>@endif @endforeach
      </div>
      <div class="ki-opts">
        <div><label for="ki-dup">Khách đã có trong danh sách</label><select id="ki-dup" wire:model.live="dup"><option value="skip">Bỏ qua, giữ thông tin cũ</option><option value="fill">Bổ sung các ô còn trống</option></select></div>
        <div><label for="ki-src">Nguồn khách cho cả lô</label><select id="ki-src" wire:model.live="src">
          <option value="__file">{{ ($this->cleanMap()['source'] ?? -1) >= 0 ? 'Theo cột Nguồn trong file' : 'Danh sách cũ' }}</option>
          @foreach(\App\Models\Customer::SOURCES as $k => $t)@if($k !== 'danh_sach_cu' || ($this->cleanMap()['source'] ?? -1) >= 0)<option value="{{ $k }}">{{ $t }}</option>@endif @endforeach
        </select></div>
        <div><label for="ki-grp">Nhóm khách khi file để trống</label><select id="ki-grp" wire:model.live="grp">
          @foreach(\App\Models\Customer::GROUPS as $k => $t)<option value="{{ $k }}">{{ $t }}</option>@endforeach
        </select></div>
      </div>
      <div class="ki-scroll"><table class="ki-tbl">
        <thead><tr><th class="num">Dòng</th><th>Họ tên</th><th>Số điện thoại</th><th>Nhóm</th><th>Kết quả</th></tr></thead>
        <tbody>
        @forelse(array_slice($rows, 0, 300) as $o)
          <tr>
            <td class="num">{{ $o['line'] }}</td>
            <td>@if($o['name'] !== '')<b>{{ $o['name'] }}</b>@else<span class="sub">trống</span>@endif @if($o['email'] !== '')<span class="sub">{{ $o['email'] }}</span>@endif</td>
            <td class="nw">{{ $o['phone'] }}@if($o['st'] !== 'err' && $o['fix'])<span class="fix">{{ implode(', ', $o['fix']) }}</span>@endif</td>
            <td>{{ \App\Models\Customer::GROUPS[$o['group'] ?: $grp] ?? '' }}</td>
            <td>
              @if($o['st'] === 'new')<span class="ki-pill ok">Thêm mới</span>
              @elseif($o['st'] === 'dupc')<span class="ki-pill warn">{{ $dup === 'fill' && $o['fill'] ? 'Bổ sung ô trống' : 'Bỏ qua' }}</span><span class="sub">{{ $o['why'] }}</span>
              @elseif($o['st'] === 'dupf')<span class="ki-pill warn">Bỏ qua</span><span class="sub">{{ $o['why'] }}</span>
              @else<span class="ki-pill bad">Lỗi</span><span class="sub">{{ $o['why'] }}</span>@endif
            </td>
          </tr>
        @empty
          <tr><td colspan="5" class="muted" style="text-align:center;padding:24px">Không có dòng nào ở mục này.</td></tr>
        @endforelse
        </tbody>
      </table></div>
      @if(count($rows) > 300)<p class="muted" style="margin-top:8px">Đang hiện 300 trên {{ number_format(count($rows), 0, ',', '.') }} dòng.</p>@endif
      @if($err)<p class="ki-err" role="alert">{{ $err }}</p>@endif
      <div class="ki-actions">
        <button type="button" class="g" wire:click="toStep(2)">Quay lại ghép cột</button>
        @if($n['err'])<button type="button" class="g" wire:click="downloadErrors">Tải {{ number_format($n['err'], 0, ',', '.') }} dòng lỗi để sửa</button>@endif
        <span class="sp"></span>
        <button type="button" class="p" wire:click="commit" wire:loading.attr="disabled" wire:target="commit" @disabled($n['new'] + $n['fill'] === 0)>Nhập {{ number_format($n['new'], 0, ',', '.') }} khách mới{{ $n['fill'] ? ', bổ sung ' . number_format($n['fill'], 0, ',', '.') : '' }}</button>
      </div>
      <div wire:loading wire:target="commit" class="ki-busy">Đang lưu…</div>
    </section>

  @else
    <section class="ki-card ki-done">
      <h2>{{ ($result['undone'] ?? false) ? 'Đã hoàn tác lô ' . ($result['id'] ?? '') : 'Đã nhập xong lô ' . ($result['id'] ?? '') }}</h2>
      <p>Thêm <b>{{ number_format($result['added'] ?? 0, 0, ',', '.') }}</b> khách mới, bổ sung {{ $result['filled'] ?? 0 }}, bỏ qua {{ $result['skipped'] ?? 0 }} dòng trùng, {{ $result['errors'] ?? 0 }} dòng lỗi không nhập.</p>
      <div class="ki-actions">
        @if(! ($result['undone'] ?? false))<button type="button" class="g" wire:click="undo({{ (int) ($result['id'] ?? 0) }})" wire:confirm="Hoàn tác lô {{ $result['id'] ?? '' }}? Khách của lô này chưa có đơn sẽ bị bỏ khỏi danh sách.">Hoàn tác lô này</button>@endif
        <button type="button" class="g" wire:click="toStep(1)">Nhập file khác</button>
        <a class="p" href="{{ $this->listUrl() }}">Xem danh sách khách</a>
      </div>
    </section>
  @endif

  @if(in_array($step, [1, 4], true))
    @php $batches = $this->batches(); @endphp
    @if($batches->isNotEmpty())
    <section class="ki-card">
      <h3>Các lô đã nhập</h3>
      <div class="ki-wrap"><table class="ki-tbl">
        <thead><tr><th>Lô</th><th>File</th><th>Người nhập</th><th>Lúc nhập</th><th class="num">Thêm mới</th><th class="num">Bổ sung</th><th class="num">Bỏ qua</th><th class="num">Lỗi</th><th></th></tr></thead>
        <tbody>
        @foreach($batches as $b)
          <tr>
            <td><b>{{ $b->id }}</b></td>
            <td>{{ $b->file_name }}</td>
            <td class="nw">{{ $b->user?->name ?? '—' }}</td>
            <td class="nw">{{ $b->created_at?->format('H:i d/m/Y') }}</td>
            <td class="num">{{ number_format($b->added_count, 0, ',', '.') }}</td>
            <td class="num">{{ $b->filled_count }}</td>
            <td class="num">{{ $b->skipped_count }}</td>
            <td class="num">{{ $b->error_count }}</td>
            <td class="nw">
              @if($b->undone_at)<span class="ki-pill warn">Đã hoàn tác</span>
              @elseif($b->canUndo(auth()->user()))<button type="button" class="g" wire:click="undo({{ $b->id }})" wire:confirm="Hoàn tác lô {{ $b->id }}? Khách của lô này chưa có đơn sẽ bị bỏ khỏi danh sách.">Hoàn tác</button>@endif
            </td>
          </tr>
        @endforeach
        </tbody>
      </table></div>
    </section>
    @endif
  @endif
</div>
</x-filament-panels::page>
