@extends('noi-bo.layout', ['title' => $t->title])
@section('content')
@php $T = \App\Models\Task::class; $H = \App\Hr\Tasks::class; @endphp
<p class="meta"><a href="/noi-bo/viec">← Việc của tôi</a></p>
<h1>{{ $t->title }}</h1>
<p class="meta" style="margin-top:-8px"><span class="pill">{{ $T::STATUS[$t->status] }}</span> @if($t->isOverdue())<span class="pill" style="background:#fde2e2;color:var(--red)">Quá hạn</span>@endif
  · Hạn {{ $H::when($t->due_at) }} · {{ $T::PRIORITY[$t->priority] }} · người làm {{ $t->employee->full_name }} · người giao {{ $t->assignerName() }}</p>
@if($t->description)<div class="card body">{{ $t->description }}</div>@endif
@if($t->link)<p><a href="{{ $t->link }}" target="_blank" rel="noopener">Mở liên kết kèm theo</a></p>@endif
@if($isMine && $t->isOpen())
  <form method="post" action="/noi-bo/viec/{{ $t->id }}/trang-thai" class="card">@csrf
    <div class="field"><label for="nt">Ghi chú kết quả (khi báo xong)</label><textarea id="nt" name="note" rows="2"></textarea></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">@if($t->status === 'todo')<button class="btn line" name="status" value="doing">Bắt đầu làm</button>@endif<button class="btn" name="status" value="done">Báo đã xong</button></div>
  </form>
@elseif(! $isMine && $t->isOpen())
  <form method="post" action="/noi-bo/viec/{{ $t->id }}/trang-thai" onsubmit="return confirm('Hủy việc này?')">@csrf<button class="btn line" name="status" value="cancelled">Hủy việc đã giao</button></form>
@endif
@if($t->done_note)<div class="card"><b>Kết quả:</b> {{ $t->done_note }}</div>@endif
<h2>Trao đổi, lịch sử</h2>
@foreach($t->updates as $u)
  <div class="card" style="padding:10px 14px"><div class="meta">{{ $u->created_at->tz('Asia/Ho_Chi_Minh')->format('H:i d/m') }} · {{ $u->kind === 'reminder' ? 'Hệ thống' : $u->author() }}@if($u->channels) · gửi qua {{ $u->channels }}@endif</div><div class="body">{{ $u->body }}</div></div>
@endforeach
<form method="post" action="/noi-bo/viec/{{ $t->id }}/binh-luan" class="card">@csrf
  <div class="field"><label for="cm">Trao đổi với {{ $isMine ? 'người giao' : 'người làm' }}</label><textarea id="cm" name="body" rows="2" required></textarea></div>
  <button class="btn line">Gửi</button>
</form>
@endsection
