<?php
namespace App\Filament\Pages;

use App\Filament\SettingsPage;
use App\Meta\Meta;
use App\Models\AppSetting;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class MetaSettings extends SettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;
    protected static ?int $navigationSort = 4;
    protected static ?string $navigationLabel = 'Facebook, Instagram';
    protected static ?string $title = 'Facebook, Instagram, Pixel';
    protected static ?string $slug = 'facebook-instagram';

    public function mount(): void
    {
        if (! AppSetting::get('meta_verify_token')) AppSetting::put('meta_verify_token', Str::random(32));
        parent::mount();
    }

    public function banner(): ?array
    {
        $parts = [Meta::inboxLive() ? '<b>Hộp thư:</b> đang gửi thật.' : '<b>Hộp thư:</b> chạy thử, trả lời chỉ lưu trong hệ thống.',
            Meta::capiReady() && AppSetting::enabled('meta_capi_live') ? '<b>Conversions API:</b> đang gửi thật.' : '<b>Conversions API:</b> chạy thử, sự kiện mua hàng chỉ ghi nhật ký.'];
        return ['tone' => Meta::inboxLive() ? 'ok' : 'info', 'html' => implode('<br>', $parts)];
    }

    public function sections(): array
    {
        return [
            'Fanpage, Instagram: tin nhắn về hộp thư chung' => ['desc' => 'Tạo ứng dụng trên developers.facebook.com, thêm Messenger và Instagram, nối Fanpage. Khai báo webhook bằng 2 dòng bên dưới, đăng ký trường <i>messages</i>.', 'fields' => [
                'meta_webhook_url' => ['label' => 'Callback URL (khai báo với Meta)', 'type' => 'info', 'value' => url('/webhooks/meta')],
                'meta_verify_token' => ['label' => 'Verify token (khai báo với Meta)', 'hint' => 'Tự tạo sẵn, có thể đổi.'],
                'meta_app_id' => ['label' => 'App ID'],
                'meta_app_secret' => ['label' => 'App secret', 'type' => 'secret', 'hint' => 'Dùng để kiểm chữ ký tin gửi tới. Chưa có thì tin bị bỏ qua.'],
                'meta_page_id' => ['label' => 'Page ID của Fanpage'],
                'meta_page_token' => ['label' => 'Page access token', 'type' => 'secret'],
                'meta_ig_id' => ['label' => 'Instagram business account ID'],
                'meta_graph_version' => ['label' => 'Phiên bản Graph API', 'default' => 'v23.0'],
                'meta_inbox_live' => ['label' => 'Bật gửi thật trả lời Messenger, Instagram', 'type' => 'toggle'],
            ]],
            'Pixel, Conversions API: đo đơn hàng từ quảng cáo' => ['desc' => 'Pixel gắn lên web khi có Pixel ID. Conversions API gửi sự kiện mua hàng từ máy chủ, trùng mã đơn nên Meta không đếm 2 lần.', 'fields' => [
                'meta_pixel_id' => ['label' => 'Pixel ID (Dataset ID)'],
                'meta_capi_token' => ['label' => 'Mã truy cập Conversions API', 'type' => 'secret'],
                'meta_test_event_code' => ['label' => 'Mã sự kiện thử (Test events)', 'hint' => 'Chỉ điền khi đang kiểm trong Events Manager, xong thì xóa.'],
                'meta_capi_live' => ['label' => 'Bật gửi thật Conversions API', 'type' => 'toggle'],
            ]],
        ];
    }

    public function buttons(): array { return ['test' => 'Kiểm tra Page token']; }

    public function test(): void
    {
        $token = AppSetting::get('meta_page_token');
        if (! $token) { $this->status = 'Chưa có Page access token.'; return; }
        try {
            $j = Http::timeout(15)->get(Meta::graph('me'), ['fields' => 'id,name', 'access_token' => $token])->json() ?? [];
            $this->status = isset($j['name']) ? 'Token dùng được cho trang: ' . $j['name'] . ' (' . $j['id'] . ')' : 'Meta báo lỗi: ' . ($j['error']['message'] ?? 'không rõ');
        } catch (\Throwable $e) { $this->status = 'Không gọi được Meta: ' . $e->getMessage(); }
    }
}
