@extends('layouts.app', ['title' => 'Hồ sơ nhân viên mới', 'noindex' => true])
@section('content')
<style>.ob .two2{display:grid;grid-template-columns:1fr 1fr;gap:0 14px}@media(max-width:640px){.ob .two2{grid-template-columns:1fr}}.ob h2{font-size:20px;margin:22px 0 10px}.ob .err{color:var(--red);font-size:13px}</style>
<div class="wrap ob" style="max-width:760px;padding-bottom:40px">
  <h1 class="h1" style="margin-top:24px">Hồ sơ nhân viên mới</h1>
  @if($done)
    <div class="box"><p><b>Đã gửi hồ sơ. Mã nhân viên của bạn: {{ $done }}.</b></p><p>Bộ phận nhân sự sẽ duyệt và gửi tài khoản đăng nhập Workplace cho bạn.</p></div>
  @elseif(! $t)
    <div class="box"><p>Đường dẫn đã hết hạn hoặc đã được dùng. Bạn liên hệ bộ phận nhân sự để nhận đường dẫn mới.</p></div>
  @else
  <p class="lead">Chào mừng bạn. Điền hồ sơ dưới đây, bộ phận nhân sự sẽ duyệt và cấp tài khoản Workplace.</p>
  <form method="post" action="/ho-so-nhan-vien" enctype="multipart/form-data" class="box">@csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <div class="two2">
      <div class="field"><label for="fn">Họ tên đầy đủ (như CCCD) *</label><input id="fn" name="full_name" value="{{ old('full_name', $t->application?->name) }}" required></div>
      <div class="field"><label for="ph">Số điện thoại *</label><input id="ph" name="phone" inputmode="tel" value="{{ old('phone', $t->application?->phone) }}" required></div>
      <div class="field"><label for="em">Email</label><input id="em" name="email" type="email" value="{{ old('email', $t->application?->email) }}"></div>
      <div class="field"><label for="dp">Phòng ban *</label><select id="dp" name="department_id" required>@foreach($departments as $d)<option value="{{ $d->id }}" @selected(old('department_id') == $d->id)>{{ $d->name }}</option>@endforeach</select></div>
      <div class="field"><label for="jt">Chức danh</label><input id="jt" name="job_title" value="{{ old('job_title', $t->application?->position) }}"></div>
      <div class="field"><label for="zl">Zalo</label><input id="zl" name="zalo" value="{{ old('zalo') }}"></div>
    </div>
    @if($insurance)<div class="two2"><div class="field"><label for="bh">Số BHXH</label><input id="bh" name="social_insurance_no" value="{{ old('social_insurance_no') }}"></div><div class="field"><label for="yt">Số BHYT</label><input id="yt" name="health_insurance_no" value="{{ old('health_insurance_no') }}"></div></div>@endif
    @if($private)
      <h2>Thông tin cá nhân</h2>
      <div class="two2">
        <div class="field"><label for="bd">Ngày sinh</label><input id="bd" name="birthday" type="date" value="{{ old('birthday') }}"></div>
        <div class="field"><label for="cc">Số CCCD</label><input id="cc" name="id_number" value="{{ old('id_number') }}"></div>
        <div class="field" style="grid-column:1/-1"><label for="ad">Địa chỉ</label><input id="ad" name="address" value="{{ old('address') }}"></div>
        <div class="field"><label for="f1">Ảnh CCCD mặt trước * (JPG, PNG, tối đa 5MB)</label><input id="f1" name="id_front" type="file" accept="image/jpeg,image/png" required></div>
        <div class="field"><label for="f2">Ảnh CCCD mặt sau *</label><input id="f2" name="id_back" type="file" accept="image/jpeg,image/png" required></div>
        <div class="field"><label for="ba">Số tài khoản nhận lương</label><input id="ba" name="bank_account" value="{{ old('bank_account') }}"></div>
        <div class="field"><label for="bn">Ngân hàng</label><input id="bn" name="bank_name" value="{{ old('bank_name') }}"></div>
        <div class="field"><label for="bo">Chủ tài khoản</label><input id="bo" name="bank_holder" value="{{ old('bank_holder') }}"></div>
        <div class="field"><label for="en">Người liên hệ khẩn cấp</label><input id="en" name="emergency_name" value="{{ old('emergency_name') }}"></div>
        <div class="field"><label for="ep">SĐT khẩn cấp</label><input id="ep" name="emergency_phone" value="{{ old('emergency_phone') }}"></div>
        <div class="field"><label for="er">Quan hệ</label><input id="er" name="emergency_relation" value="{{ old('emergency_relation') }}"></div>
        <div class="field"><label for="dg">Bằng cấp (tối đa 12 tệp)</label><input id="dg" name="degrees[]" type="file" multiple accept=".pdf,image/jpeg,image/png"></div>
        <div class="field"><label for="hc">Giấy khám sức khỏe (tối đa 6 tệp)</label><input id="hc" name="health[]" type="file" multiple accept=".pdf,image/jpeg,image/png"></div>
      </div>
    @endif
    @foreach($errors->all() as $er)<p class="err">{{ $er }}</p>@endforeach
    <button class="btn btn-red">Gửi hồ sơ</button>
  </form>
  @endif
</div>
@endsection
