<x-filament-panels::page>
<style>.zs{max-width:760px;background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:20px}.dark .zs{background:#18181b;border-color:#3f3f46}
.zs label{display:block;font-size:12px;font-weight:600;opacity:.75;margin:12px 0 4px}.zs input{width:100%;border:1px solid #d1d5db;border-radius:10px;padding:9px 11px;background:transparent}
.zs .row{display:flex;gap:10px;margin-top:18px;flex-wrap:wrap}.zs button{border-radius:10px;padding:10px 16px;font-weight:600;border:0;cursor:pointer}
.zs .p{background:#0f766e;color:#fff}.zs .g{background:#f3f4f6}.zs .hint{font-size:12px;opacity:.7;margin-top:3px}
.zs .mode{padding:12px 14px;border-radius:10px;margin-bottom:6px;font-size:14px}</style>
<div class="zs">
  @if(! $this->has('zalo_access_token'))
    <div class="mode" style="background:#eff6ff;color:#1e40af"><b>Đang ở chế độ chạy thử.</b> Hệ thống ghi lại tin sẽ gửi vào "Nhật ký gửi tin" nhưng không gửi thật. Nhập thông tin bên dưới khi công ty đã có Zalo OA và ứng dụng trên Zalo for Developers.</div>
  @else
    <div class="mode" style="background:#ecfdf5;color:#065f46"><b>Đã có access token.</b> Mẫu nào có "Mã mẫu Zalo" sẽ được gửi thật.</div>
  @endif
  <label>OA ID</label><input wire:model="f.zalo_oa_id" placeholder="Mã Official Account">
  <label>App ID (Zalo for Developers)</label><input wire:model="f.zalo_app_id">
  @foreach(['zalo_app_secret' => 'App secret key', 'zalo_access_token' => 'Access token', 'zalo_refresh_token' => 'Refresh token', 'zalo_oa_secret' => 'OA secret key (kiểm chữ ký webhook)'] as $k => $lbl)
    <label>{{ $lbl }}</label><input type="password" wire:model="f.{{ $k }}" placeholder="{{ $this->has($k) ? 'Đã lưu (mã hóa). Để trống nếu không đổi' : 'Chưa có' }}" autocomplete="off">
  @endforeach
  <p class="hint">Các mã bí mật được mã hóa khi lưu, không hiện lại. Access token tự gia hạn mỗi 15 phút bằng refresh token.</p>
  <label>Webhook URL (khai báo trong Zalo for Developers, bật sự kiện người dùng gửi tin, gửi thông tin)</label><input readonly value="{{ url('/webhooks/zalo') }}" onclick="this.select()">
  <label style="display:flex;gap:8px;align-items:center;margin-top:14px;font-size:14px;opacity:1"><input type="checkbox" wire:model="inboxLive" style="width:18px;height:18px"> Bật gửi thật trả lời khách trong hộp thư chung (tin tư vấn OA)</label>
  <div class="row"><button class="p" wire:click="save">Lưu</button><button class="g" wire:click="test">Kiểm tra kết nối</button></div>
  @if($status)<p style="margin-top:12px">{{ $status }}</p>@endif
</div>
</x-filament-panels::page>
