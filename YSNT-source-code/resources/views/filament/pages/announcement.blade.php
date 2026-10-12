<x-filament-panels::page>
@php $a = $this->record->load(['recipients.employee.department']); $st = ['ok' => 'Đã gửi', 'sent' => 'Đã gửi', 'dry_run' => 'Chạy thử', 'error' => 'Lỗi', 'failed' => 'Lỗi', 'skipped' => 'Không có địa chỉ']; @endphp
<style>.an{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:16px 18px}.dark .an{background:#18181b;border-color:#3f3f46}.an p{white-space:pre-wrap;margin:6px 0}
.an table{width:100%;border-collapse:collapse;font-size:13px}.an td,.an th{text-align:left;padding:7px 8px;border-bottom:1px solid #f3f4f6}.dark .an td,.dark .an th{border-color:#27272a}.an th{font-size:12px;opacity:.65}
.an .k{display:flex;gap:18px;flex-wrap:wrap;font-size:14px}.an .k b{font-size:20px;display:block}</style>
<div class="an"><p class="k"><span><b>{{ $a->recipient_count }}</b>người nhận</span><span><b>{{ $a->recipients->whereNotNull('read_at')->count() }}</b>đã đọc</span><span><b>{{ $a->email_ok }}</b>email đi</span><span><b>{{ $a->zalo_ok }}</b>Zalo đi</span></p>
  <p style="opacity:.7;font-size:13px">{{ $a->created_at->format('H:i d/m/Y') }} · {{ $a->sender?->name }} · {{ collect($a->channels)->map(fn ($c) => \App\Models\Announcement::CHANNELS[$c] ?? $c)->implode(', ') }}</p>
  <p>{{ $a->body }}</p>@if($a->link)<p><a href="{{ $a->link }}" target="_blank" style="text-decoration:underline">{{ $a->link }}</a></p>@endif</div>
<div class="an" style="overflow-x:auto"><table><thead><tr><th>Nhân viên</th><th>Phòng ban</th><th>Đã đọc</th><th>Email</th><th>Zalo</th></tr></thead><tbody>
@foreach($a->recipients->sortBy(fn ($r) => $r->read_at ? 1 : 0) as $r)
<tr><td>{{ $r->employee?->full_name }}</td><td>{{ $r->employee?->department?->name }}</td><td>{{ $r->read_at?->format('H:i d/m') ?? 'Chưa' }}</td><td>{{ $r->email_status ? ($st[$r->email_status] ?? $r->email_status) : '—' }}</td><td>{{ $r->zalo_status ? ($st[$r->zalo_status] ?? $r->zalo_status) : '—' }}</td></tr>
@endforeach</tbody></table></div>
</x-filament-panels::page>
