@extends('noi-bo.layout', ['title' => 'Đăng nhập'])
@section('content')
<div class="login">
  <h1>Workplace {{ \App\Support\Company::get('brand_name') }}</h1>
  <p class="meta" style="margin-top:-8px">Dành cho nhân viên công ty. Tài khoản do bộ phận nhân sự cấp.</p>
  @if($otp)<div class="tabs" role="tablist"><a href="/noi-bo/dang-nhap" @if($tab === 'pass') aria-current="page" @endif>Mật khẩu</a><a href="/noi-bo/dang-nhap?cach=otp" @if($tab === 'otp') aria-current="page" @endif>Mã OTP</a></div>@endif
  <div class="card">
  @if($tab === 'pass')
    <form method="post" action="/noi-bo/dang-nhap">@csrf
      <div class="field"><label for="phone">Số điện thoại</label><input id="phone" name="phone" inputmode="tel" autocomplete="username" value="{{ old('phone') }}" required></div>
      <div class="field"><label for="password">Mật khẩu</label><input id="password" name="password" type="password" autocomplete="current-password" required></div>
      @error('phone')<p class="err">{{ $message }}</p>@enderror
      <button class="btn" style="width:100%">Đăng nhập</button>
    </form>
  @elseif(! $sent)
    <form method="post" action="/noi-bo/otp/gui">@csrf
      <div class="field"><label for="phone">Số điện thoại</label><input id="phone" name="phone" inputmode="tel" autocomplete="username" value="{{ old('phone') }}" required></div>
      @error('phone')<p class="err">{{ $message }}</p>@enderror
      <button class="btn" style="width:100%">Gửi mã qua Zalo / SMS</button>
    </form>
  @else
    <form method="post" action="/noi-bo/otp/xac-nhan">@csrf
      <input type="hidden" name="phone" value="{{ $sent }}">
      <p class="meta">Nếu số {{ $sent }} có tài khoản, mã 6 số đã được gửi. Mã hết hạn sau 5 phút.</p>
      <div class="field"><label for="code">Mã OTP</label><input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required></div>
      @error('code')<p class="err">{{ $message }}</p>@enderror
      <button class="btn" style="width:100%">Đăng nhập</button>
      <p class="meta" style="margin-top:10px"><a href="/noi-bo/dang-nhap?cach=otp">Gửi lại mã</a></p>
    </form>
  @endif
  </div>
  <p class="meta">Quên mật khẩu: liên hệ bộ phận nhân sự để được cấp lại.</p>
</div>
@endsection
