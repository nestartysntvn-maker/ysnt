@extends('noi-bo.layout', ['title' => 'Tuyển dụng'])
@section('content')
<h1>Đang tuyển: giới thiệu người quen</h1>
@forelse($jobs as $j)
  <div class="card"><b>{{ $j->title }}</b> <span class="pill">{{ \App\Models\RecruitmentJob::TYPES[$j->employment_type] ?? '' }}</span>
    <div class="meta">{{ $j->location }}@if($j->salary) · {{ $j->salary }}@endif @if($j->deadline) · hạn {{ $j->deadline->format('d/m/Y') }}@endif</div>
    @if($j->description)<details style="margin-top:6px"><summary>Mô tả công việc</summary><div class="body">{{ $j->description }}@if($j->requirements)

Yêu cầu:
{{ $j->requirements }}@endif</div></details>@endif
  </div>
@empty
  <div class="card">Hiện chưa có vị trí đang tuyển.</div>
@endforelse
@if($jobs->isNotEmpty())
<form method="post" action="/noi-bo/tuyen-dung" class="card">@csrf
  <h2 style="margin-top:0">Giới thiệu ứng viên</h2>
  <div class="field"><label for="job">Vị trí</label><select id="job" name="job_id">@foreach($jobs as $j)<option value="{{ $j->id }}">{{ $j->title }}</option>@endforeach</select></div>
  <div class="grid2"><div class="field"><label for="n">Họ tên ứng viên</label><input id="n" name="name" value="{{ old('name') }}" required></div>
  <div class="field"><label for="p">Số điện thoại</label><input id="p" name="phone" inputmode="tel" value="{{ old('phone') }}" required></div></div>
  <div class="field"><label for="m">Email (nếu có)</label><input id="m" name="email" type="email" value="{{ old('email') }}"></div>
  <div class="field"><label for="g">Vì sao bạn giới thiệu người này</label><textarea id="g" name="message" rows="3">{{ old('message') }}</textarea></div>
  @foreach($errors->all() as $er)<p class="err">{{ $er }}</p>@endforeach
  <button class="btn">Gửi giới thiệu</button>
</form>
@endif
@if($mine->isNotEmpty())<h2>Bạn đã giới thiệu</h2>@foreach($mine as $a)<div class="card">{{ $a->name }} · {{ $a->position }} <span class="pill {{ $a->status === 'hired' ? 'ok' : '' }}">{{ \App\Models\JobApplication::STATUS[$a->status] }}</span><div class="meta">{{ $a->code }} · {{ $a->created_at->format('d/m/Y') }}</div></div>@endforeach @endif
@endsection
