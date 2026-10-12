<x-filament-panels::page>
<style>.rm{background:#fff;border:1px solid #e5e7eb;border-radius:14px;overflow-x:auto}.dark .rm{background:#18181b;border-color:#3f3f46}.rm table{width:100%;border-collapse:collapse;font-size:13px}
.rm th,.rm td{text-align:left;padding:8px 10px;border-bottom:1px solid #f3f4f6;vertical-align:middle}.dark .rm th,.dark .rm td{border-color:#27272a}.rm th{font-size:12px;opacity:.65}
.rm select,.rm input{border:1px solid #d1d5db;border-radius:8px;padding:6px 8px;background:transparent;font-size:13px;width:100%}.dark .rm select,.dark .rm input{border-color:#52525b}.rm select option{color:#111}
.rm .late{color:#b91c1c;font-weight:600}.rmb{border-radius:10px;padding:10px 16px;font-weight:600;border:0;cursor:pointer;background:#0f766e;color:#fff}</style>
@php $admin = auth()->user()?->role === 'admin'; @endphp
<div class="rm"><table><thead><tr><th>Hạng mục</th><th>Nhóm</th><th>Quý dự kiến</th><th style="min-width:190px">Tình trạng</th><th style="min-width:240px">Ghi chú, còn thiếu</th></tr></thead><tbody>
@foreach(\App\Support\Roadmap::all() as $i)
<tr><td style="font-weight:500">{{ $i['title'] }}</td><td style="opacity:.75">{{ $i['group'] }}</td><td style="white-space:nowrap">{{ $i['target'] ?? '—' }}@if($i['late']) <span class="late">· trễ</span>@endif</td>
<td><select wire:model="st.{{ $i['code'] }}" @disabled(! $admin) aria-label="Tình trạng {{ $i['title'] }}">@foreach(\App\Support\Roadmap::STATUS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></td>
<td><input wire:model="note.{{ $i['code'] }}" @disabled(! $admin) aria-label="Ghi chú {{ $i['title'] }}"></td></tr>
@endforeach</tbody></table></div>
@if($admin)<div><button class="rmb" wire:click="save">Lưu</button></div>@endif
</x-filament-panels::page>
