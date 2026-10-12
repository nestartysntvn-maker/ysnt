@extends('layouts.app')
@section('content')
@php $C = \App\Support\Company::class; $open = $jobs->where('status', 'open'); @endphp
<style>.rj{display:grid;gap:12px;margin:18px 0 30px}.rj .box{padding:16px 18px}.rj .box.off{opacity:.55}.rj h3{margin:0 0 4px;font-size:18px}.rj .m{color:var(--ink2);font-size:14px}
.rj .tag{display:inline-block;border-radius:999px;padding:2px 10px;font-size:12px;font-weight:600;background:var(--sea50);color:var(--sea);margin-left:6px}.rj .tag.off{background:#eee;color:#555}
.rj details{margin-top:8px}.rj .body{white-space:pre-wrap;font-size:14px;margin-top:6px}.rf .two2{display:grid;grid-template-columns:1fr 1fr;gap:0 14px}@media(max-width:640px){.rf .two2{grid-template-columns:1fr}}
.rf .err{color:var(--red);font-size:13px}.okm{background:#DCFCE7;color:#166534;border-radius:12px;padding:12px 14px;margin-bottom:14px}.hp{position:absolute;left:-9999px}</style>
<div class="wrap">
  <div class="crumb"><a href="/">Trang chủ</a> › Tuyển dụng</div>
  <h1 class="h1">Tuyển dụng</h1>
  <p class="lead">{{ $intro ?: 'Gia nhập ' . $C::get('company_name') . ': nhà yến, nhà máy đạt chứng nhận FDA và hệ thống bán hàng đang lớn nhanh tại Nha Trang.' }}</p>
  <div class="rj">
  @forelse($jobs as $j)
    <article class="box {{ $j->status === 'open' ? '' : 'off' }}">
      <h3>{{ $j->title }} @if($j->status === 'open')<span class="tag">{{ \App\Models\RecruitmentJob::TYPES[$j->employment_type] ?? '' }}</span>@else<span class="tag off">Đã dừng tuyển</span>@endif</h3>
      <div class="m">{{ $j->department?->name }}@if($j->department) · @endif{{ $j->location }}@if($j->salary) · {{ $j->salary }}@endif @if($j->deadline && $j->status === 'open') · hạn nộp {{ $j->deadline->format('d/m/Y') }}@endif</div>
      @if($j->status === 'open' && ($j->description || $j->requirements))<details><summary>Xem mô tả, yêu cầu</summary>
        @if($j->description)<div class="body">{{ $j->description }}</div>@endif
        @if($j->requirements)<p style="margin:10px 0 0"><b>Yêu cầu</b></p><ul>@foreach(preg_split('/\r?\n/', trim($j->requirements)) as $l)@if(trim($l) !== '')<li>{{ ltrim($l, '-• ') }}</li>@endif @endforeach</ul>@endif
        @if($j->benefits)<p style="margin:10px 0 0"><b>Quyền lợi</b></p><div class="body">{{ $j->benefits }}</div>@endif
      </details>@endif
    </article>
  @empty
    <div class="empty">Hiện chưa có vị trí đang tuyển. Bạn vẫn có thể gửi hồ sơ, chúng tôi lưu lại cho đợt tuyển sau.</div>
  @endforelse
  </div>
  <section class="box rf" id="nop" style="max-width:720px;margin-bottom:40px">
    <h2 class="h2" style="font-size:24px">Nộp hồ sơ</h2>
    @if(session('ok'))<div class="okm" role="status">{{ session('ok') }}</div>@endif
    <form method="post" action="/tuyen-dung" enctype="multipart/form-data">@csrf
      <input class="hp" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
      <div class="field"><label for="job">Vị trí ứng tuyển</label><select id="job" name="job_id">@foreach($open as $j)<option value="{{ $j->id }}" @selected(old('job_id') == $j->id)>{{ $j->title }}</option>@endforeach<option value="" @selected(old('job_id') === '')>Khác</option></select></div>
      <div class="field"><label for="pos">Vị trí khác (nếu chọn Khác)</label><input id="pos" name="position" value="{{ old('position') }}"></div>
      <div class="two2"><div class="field"><label for="n">Họ tên *</label><input id="n" name="name" value="{{ old('name') }}" required autocomplete="name"></div>
      <div class="field"><label for="p">Số điện thoại *</label><input id="p" name="phone" value="{{ old('phone') }}" inputmode="tel" required autocomplete="tel"></div>
      <div class="field"><label for="e">Email *</label><input id="e" name="email" type="email" value="{{ old('email') }}" required autocomplete="email"></div>
      <div class="field"><label for="x">Kinh nghiệm</label><select id="x" name="experience"><option value="">Chọn</option>@foreach(\App\Models\JobApplication::EXPERIENCE as $k => $l)<option value="{{ $k }}" @selected(old('experience') === $k)>{{ $l }}</option>@endforeach</select></div></div>
      <div class="two2"><div class="field"><label for="cv">CV (PDF, Word hoặc ảnh, tối đa 10MB)</label><input id="cv" name="cv" type="file" accept=".pdf,.doc,.docx,image/jpeg,image/png"></div>
      <div class="field"><label for="cu">Hoặc đường dẫn CV</label><input id="cu" name="cv_url" type="url" value="{{ old('cv_url') }}" placeholder="https://"></div></div>
      <div class="field"><label for="m">Giới thiệu ngắn về bạn *</label><textarea id="m" name="message" rows="4" required>{{ old('message') }}</textarea></div>
      @foreach($errors->all() as $er)<p class="err">{{ $er }}</p>@endforeach
      <button class="btn btn-red">Gửi hồ sơ</button>
      <p class="note" style="font-size:12px;color:var(--ink2)">Thông tin chỉ dùng cho việc tuyển dụng của {{ $C::get('company_name') }}.</p>
    </form>
  </section>
</div>
@endsection
