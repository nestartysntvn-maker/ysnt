<x-filament-widgets::widget>
  <x-filament::section heading="Thông báo từ công ty">
    @php $list = $this->notices(); @endphp
    @if($this->canSend())<x-slot name="afterHeader"><a href="{{ $this->createUrl() }}" style="font-size:13px;font-weight:600;text-decoration:underline">Soạn thông báo</a></x-slot>
    @endif
    @forelse($list as $a)
      <div style="padding:9px 0;border-bottom:1px solid rgba(128,128,128,.15)">
        <div style="font-size:12px;opacity:.65">{{ $a->created_at->format('H:i d/m/Y') }} · {{ $a->sender?->name ?? 'Công ty' }}@if($a->pinned) · Ghim @endif
          @if($a->recipient_count) · đã đọc {{ $a->read_count }}/{{ $a->recipient_count }}@endif</div>
        <div style="font-weight:600;font-size:14px">{{ $a->title }}</div>
        <div style="font-size:13px;opacity:.8">{{ \Illuminate\Support\Str::limit($a->body, 140) }}</div>
      </div>
    @empty
      <p style="font-size:14px;opacity:.7">Chưa có thông báo nào. Thông báo gửi cho nhân viên sẽ hiện ở đây và trên Workplace.</p>
    @endforelse
    @if($list->isNotEmpty())<p style="margin-top:8px;font-size:13px"><a href="{{ $this->listUrl() }}" style="text-decoration:underline">Xem tất cả</a></p>@endif
  </x-filament::section>
</x-filament-widgets::widget>
