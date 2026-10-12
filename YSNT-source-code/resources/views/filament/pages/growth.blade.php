<x-filament-panels::page>
<style>
.gr-tbl{background:#fff;border:1px solid #e5e7eb;border-radius:14px;overflow:auto}.dark .gr-tbl{background:#18181b;border-color:#3f3f46}
.gr-tbl table{border-collapse:collapse;width:100%;font-size:13px;white-space:nowrap}.gr-tbl th,.gr-tbl td{padding:8px 12px;border-bottom:1px solid #f3f4f6;text-align:right}
.dark .gr-tbl th,.dark .gr-tbl td{border-color:#27272a}.gr-tbl th{font-weight:600;font-size:12px;opacity:.7;position:sticky;top:0;background:inherit}.gr-tbl td:first-child,.gr-tbl th:first-child{text-align:left;position:sticky;left:0;background:inherit}
.gr-tbl h3{font-weight:700;font-size:15px;padding:14px 16px 6px;margin:0;white-space:normal}
</style>
<section class="gr-tbl" aria-label="Bảng số liệu theo tháng">
  <h3>Bảng số liệu theo tháng</h3>
  @php $rows = static::rows(); $money = fn ($k) => str_starts_with($k, 'Doanh') || str_starts_with($k, 'Chỉ tiêu'); @endphp
  <table><thead><tr>@foreach(array_keys($rows[0] ?? ['Tháng' => '']) as $k)<th scope="col">{{ $k }}</th>@endforeach</tr></thead>
  <tbody>@foreach($rows as $r)<tr>@foreach($r as $k => $v)<td>{{ $k === 'Tháng' ? $v : ($money($k) ? \App\Support\Targets::ty((int) $v) : number_format((int) $v, 0, ',', '.')) }}</td>@endforeach</tr>@endforeach</tbody></table>
</section>
</x-filament-panels::page>
