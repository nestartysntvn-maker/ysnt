@extends('noi-bo.layout', ['title' => 'Việc của tôi'])
@section('content')
@php $T = \App\Models\Task::class; $H = \App\Hr\Tasks::class; @endphp
<h1>Việc của tôi</h1>
@forelse($open as $t)
  <a class="card {{ $t->isOverdue() ? 'unread' : '' }}" href="/noi-bo/viec/{{ $t->id }}" style="display:block;text-decoration:none;color:inherit{{ $t->isOverdue() ? ';border-left-color:var(--red)' : '' }}">
    <div class="meta">@if($t->isOverdue())<span class="pill" style="background:#fde2e2;color:var(--red)">Quá hạn</span>@elseif($t->due_at && $t->due_at->isToday())<span class="pill amb">Hôm nay</span>@endif
      @if(in_array($t->priority, ['high', 'urgent']))<span class="pill amb">{{ $T::PRIORITY[$t->priority] }}</span>@endif <span class="pill">{{ $T::STATUS[$t->status] }}</span></div>
    <b style="display:block;margin-top:6px">{{ $t->title }}</b>
    <div class="meta">Hạn: {{ $H::when($t->due_at) }} · người giao: {{ $t->assignerName() }}</div>
  </a>
@empty
  <div class="card">Bạn không còn việc nào đang mở.</div>
@endforelse
@if($done->isNotEmpty())<h2>Đã xong 14 ngày qua</h2>@foreach($done as $t)<a class="card" href="/noi-bo/viec/{{ $t->id }}" style="display:block;text-decoration:none;color:inherit;opacity:.75"><b>{{ $t->title }}</b><div class="meta">Xong lúc {{ $H::when($t->done_at) }}</div></a>@endforeach @endif

@if($team->isNotEmpty())
  <h2>Giao việc cho nhóm</h2>
  <form method="post" action="/noi-bo/viec/giao" class="card">@csrf
    <div class="field"><label>Người nhận (chọn một hoặc nhiều)</label>
      <div style="display:flex;flex-wrap:wrap;gap:8px">@foreach($team as $m)<label style="display:flex;gap:6px;align-items:center;border:1px solid var(--line);border-radius:10px;padding:6px 10px;font-weight:500"><input type="checkbox" name="employee_ids[]" value="{{ $m->id }}" @checked(in_array($m->id, old('employee_ids', [])))> {{ $m->full_name }}</label>@endforeach</div></div>
    <div class="field"><label for="tt">Việc cần làm</label><input id="tt" name="title" value="{{ old('title') }}" required maxlength="255"></div>
    <div class="field"><label for="td">Mô tả, yêu cầu kết quả</label><textarea id="td" name="description" rows="3">{{ old('description') }}</textarea></div>
    <div class="grid2"><div class="field"><label for="tdue">Hạn chót</label><input id="tdue" type="datetime-local" name="due_at" value="{{ old('due_at') }}"></div>
      <div class="field"><label for="tp">Mức ưu tiên</label><select id="tp" name="priority">@foreach($T::PRIORITY as $k => $l)<option value="{{ $k }}" @selected(old('priority', 'normal') === $k)>{{ $l }}</option>@endforeach</select></div>
      <div class="field"><label for="tr">Nhắc việc</label><select id="tr" name="remind_before_hours">@foreach($T::REMIND as $k => $l)<option value="{{ $k }}" @selected((int) old('remind_before_hours', 24) === $k)>{{ $l }}</option>@endforeach</select></div></div>
    @foreach($errors->all() as $er)<p class="err">{{ $er }}</p>@endforeach
    <button class="btn">Giao việc</button>
    <p class="meta" style="margin-top:8px">Người nhận thấy việc trên Workplace và nhận tin Zalo, email (khi công ty đã bật). Hệ thống tự nhắc trước hạn và báo cả hai bên khi quá hạn.</p>
  </form>
  @if($given->isNotEmpty())<h2>Việc tôi đã giao</h2>
    <div class="card" style="padding:4px 12px;overflow-x:auto"><table class="dir"><thead><tr><th>Việc</th><th>Người làm</th><th>Hạn</th><th>Tình trạng</th></tr></thead><tbody>
    @foreach($given as $t)<tr><td><a href="/noi-bo/viec/{{ $t->id }}">{{ $t->title }}</a></td><td>{{ $t->employee->full_name }}</td><td @if($t->isOverdue()) style="color:var(--red);font-weight:600" @endif>{{ $H::when($t->due_at) }}</td><td>{{ $T::STATUS[$t->status] }}</td></tr>@endforeach
    </tbody></table></div>@endif
@endif
@endsection
