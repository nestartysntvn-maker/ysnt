@extends('noi-bo.layout', ['title' => 'Của tôi'])
@section('content')
<h1>{{ $e->full_name }}</h1>
<p class="meta" style="margin-top:-8px"><a href="/noi-bo/danh-ba">Danh bạ</a> · <a href="/noi-bo/tuyen-dung">Tuyển dụng, giới thiệu người quen</a></p>
<p class="meta">{{ $e->code }} · {{ $e->job_title }} · {{ $e->department?->name ?? 'Chưa xếp phòng' }}@if($e->manager) · quản lý: {{ $e->manager->full_name }}@endif</p>
@if($e->must_change_password)
<form method="post" action="/noi-bo/doi-mat-khau" class="card" id="doi-mat-khau">@csrf
  <h2 style="margin-top:0">Đặt mật khẩu mới</h2>
  <div class="grid2"><div class="field"><label for="pw">Mật khẩu mới (ít nhất 8 ký tự)</label><input id="pw" name="password" type="password" autocomplete="new-password" required></div>
  <div class="field"><label for="pw2">Nhập lại</label><input id="pw2" name="password_confirmation" type="password" autocomplete="new-password" required></div></div>
  @foreach($errors->all() as $er)<p class="err">{{ $er }}</p>@endforeach
  <button class="btn">Lưu mật khẩu</button>
</form>
@else
@if($sales)<div class="grid2"><div class="card stat"><span class="meta">Mã giới thiệu của bạn</span><b>{{ $e->ambassador?->ref_code }}</b><span class="meta">Khách giảm giá, bạn hưởng hoa hồng</span></div>
  <div class="card stat"><span class="meta">Tháng này</span><b>{{ number_format($sales['revenue'], 0, ',', '.') }}đ</b><span class="meta">{{ $sales['orders'] }} đơn · hoa hồng chờ {{ number_format($sales['commission'], 0, ',', '.') }}đ</span></div></div>@endif
<form method="post" action="/noi-bo/toi" class="card">@csrf
  <h2 style="margin-top:0">Thông tin của tôi</h2>
  <p class="meta">Họ tên, chức danh, phòng ban do nhân sự cập nhật.</p>
  @if($contact)<div class="grid2">
    <div class="field"><label for="em">Email</label><input id="em" name="email" type="email" value="{{ $e->email }}"></div>
    <div class="field"><label for="zl">Zalo</label><input id="zl" name="zalo" value="{{ $e->p('zalo') }}"></div>
    <div class="field"><label for="pp">Số cá nhân khác</label><input id="pp" name="personal_phone" value="{{ $e->p('personal_phone') }}"></div>
    <div class="field"><label for="tg">Telegram</label><input id="tg" name="telegram" value="{{ $e->p('telegram') }}"></div></div>@endif
  @if($insurance)<div class="grid2"><div class="field"><label for="bh">Số BHXH</label><input id="bh" name="social_insurance_no" value="{{ $e->p('social_insurance_no') }}"></div>
    <div class="field"><label for="yt">Số BHYT</label><input id="yt" name="health_insurance_no" value="{{ $e->p('health_insurance_no') }}"></div></div>@endif
  @if($private)<div class="grid2">
    <div class="field"><label for="bd">Ngày sinh</label><input id="bd" name="birthday" type="date" value="{{ $e->priv('birthday') }}"></div>
    <div class="field"><label for="ad">Địa chỉ</label><input id="ad" name="address" value="{{ $e->priv('address') }}"></div>
    <div class="field"><label for="en">Người liên hệ khẩn cấp</label><input id="en" name="emergency_name" value="{{ $e->priv('emergency_name') }}"></div>
    <div class="field"><label for="ep">SĐT khẩn cấp</label><input id="ep" name="emergency_phone" value="{{ $e->priv('emergency_phone') }}"></div>
    <div class="field"><label for="er">Quan hệ</label><input id="er" name="emergency_relation" value="{{ $e->priv('emergency_relation') }}"></div>
    <div class="field"><label for="ba">Số tài khoản nhận lương</label><input id="ba" name="bank_account" value="{{ $e->priv('bank_account') }}"></div>
    <div class="field"><label for="bn">Ngân hàng</label><input id="bn" name="bank_name" value="{{ $e->priv('bank_name') }}"></div>
    <div class="field"><label for="bo">Chủ tài khoản</label><input id="bo" name="bank_holder" value="{{ $e->priv('bank_holder') }}"></div></div>@endif
  @foreach($errors->all() as $er)<p class="err">{{ $er }}</p>@endforeach
  <button class="btn">Lưu</button>
</form>
<form method="post" action="/noi-bo/doi-mat-khau" class="card">@csrf
  <h2 style="margin-top:0">Đổi mật khẩu</h2>
  <div class="field"><label for="cu">Mật khẩu hiện tại</label><input id="cu" name="current" type="password" autocomplete="current-password"></div>
  <div class="grid2"><div class="field"><label for="pw">Mật khẩu mới</label><input id="pw" name="password" type="password" autocomplete="new-password"></div>
  <div class="field"><label for="pw2">Nhập lại</label><input id="pw2" name="password_confirmation" type="password" autocomplete="new-password"></div></div>
  <button class="btn line">Đổi mật khẩu</button>
</form>
@endif
<form method="post" action="/noi-bo/dang-xuat">@csrf<button class="btn line">Đăng xuất</button></form>
@endsection
