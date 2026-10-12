<x-filament-widgets::widget>
  @php $d = $this->data(); $S = \App\Support\Roadmap::STATUS; @endphp
  <x-filament::section heading="Hướng chưa triển khai">
    <x-slot name="afterHeader"><a href="{{ $d['roadmapUrl'] }}" style="font-size:13px;font-weight:600;text-decoration:underline">Cập nhật lộ trình</a></x-slot>
    <p style="font-size:14px;margin:0 0 10px">Kế hoạch: <b>{{ $d['counts']['xong'] }}</b> hạng mục đang chạy thật, <b>{{ $d['counts']['dang'] }}</b> đang làm hoặc chờ thông tin, <b>{{ $d['counts']['chua'] }}</b> chưa làm{!! $d['late'] ? ', <b style="color:#b91c1c">' . (int) $d['late'] . ' trễ so với quý cam kết</b>' : '' !!}.
      Kết nối: <a href="{{ $d['statusUrl'] }}" style="text-decoration:underline"><b>{{ $d['missing']->count() }}</b> mục chưa xong</a>.</p>
    <style>@media (max-width:640px){.rmw .g,.rmw .n{display:none}}</style>
    <div style="overflow-x:auto" class="rmw"><table style="width:100%;border-collapse:collapse;font-size:13px">
      <thead><tr style="text-align:left;opacity:.65;font-size:12px"><th style="padding:6px">Hạng mục</th><th class="g" style="padding:6px">Nhóm</th><th style="padding:6px">Quý dự kiến</th><th style="padding:6px">Tình trạng</th><th class="n" style="padding:6px">Còn thiếu</th></tr></thead>
      <tbody>@foreach($d['open']->take(12) as $i)
        <tr style="border-top:1px solid rgba(128,128,128,.15)"><td style="padding:7px 6px;font-weight:500">{{ $i['title'] }}</td><td class="g" style="padding:7px 6px;opacity:.75">{{ $i['group'] }}</td>
          <td style="padding:7px 6px;white-space:nowrap">{{ $i['target'] ?? '—' }}@if($i['late']) <span style="color:#b91c1c;font-weight:600">· trễ</span>@endif</td>
          <td style="padding:7px 6px;white-space:nowrap">{{ $S[$i['status']] }}</td><td class="n" style="padding:7px 6px;opacity:.75">{{ $i['note'] ?: '—' }}</td></tr>
      @endforeach</tbody></table></div>
    @if($d['open']->count() > 12)<p style="font-size:13px;margin-top:8px"><a href="{{ $d['roadmapUrl'] }}" style="text-decoration:underline">Xem đủ {{ $d['open']->count() }} hạng mục</a></p>@endif
  </x-filament::section>
</x-filament-widgets::widget>
