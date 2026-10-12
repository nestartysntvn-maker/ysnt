<?php
namespace App\Http\Controllers;

use App\Channels\Connectors\Marketplace;
use App\Filament\Resources\SalesChannels\SalesChannelResource;
use App\Models\ChannelSyncLog;
use App\Models\SalesChannel;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** "Cấp quyền gian hàng": send the admin to the marketplace, come back with a code, store the tokens on the channel. */
class MarketplaceAuthController extends Controller
{
    public static function callbackUrl(string $platform): string { return url('/ket-noi-san/callback/' . $platform); }

    private function admin(): void { abort_unless(auth()->user()?->is_active && auth()->user()?->role === 'admin', 403); }

    public function start(SalesChannel $channel)
    {
        $this->admin();
        $m = Marketplace::make($channel->platform);
        abort_unless($m, 404);
        if (! Marketplace::hasApp($channel)) {
            Notification::make()->title('Nhập app key và app secret của sàn trước khi cấp quyền')->danger()->send();
            return redirect(SalesChannelResource::getUrl('edit', ['record' => $channel]));
        }
        $state = Str::random(32);
        session(['mp_oauth' => ['channel' => $channel->id, 'state' => $state]]);
        try {
            return redirect()->away($m->authorizeUrl($channel, self::callbackUrl($channel->platform), $state));
        } catch (\Throwable $e) {
            Notification::make()->title('Chưa mở được trang cấp quyền')->body($e->getMessage())->danger()->send();
            return redirect(SalesChannelResource::getUrl('edit', ['record' => $channel]));
        }
    }

    public function callback(Request $r, string $platform)
    {
        if (! auth()->check()) return redirect('/admin/login');
        $this->admin();
        $s = session()->pull('mp_oauth');
        $channel = $s ? SalesChannel::find($s['channel']) : null;
        abort_unless($channel && $channel->platform === $platform, 400, 'Phiên cấp quyền đã hết hạn, bấm Cấp quyền gian hàng lại.');
        abort_if($r->filled('state') && ! hash_equals($s['state'], (string) $r->query('state')), 400, 'Mã state không khớp');
        try {
            Marketplace::make($platform)->exchange($channel, $r->query());
            $channel->update(['status' => 'active', 'last_error' => null]);
            ChannelSyncLog::create(['sales_channel_id' => $channel->id, 'kind' => 'token', 'status' => 'ok', 'message' => 'Cấp quyền gian hàng thành công']);
            Notification::make()->title('Đã cấp quyền gian hàng ' . $channel->name)->success()->send();
        } catch (\Throwable $e) {
            $channel->update(['status' => 'error', 'last_error' => mb_substr('Cấp quyền lỗi: ' . $e->getMessage(), 0, 500)]);
            ChannelSyncLog::create(['sales_channel_id' => $channel->id, 'kind' => 'token', 'status' => 'error', 'message' => mb_substr($e->getMessage(), 0, 2000)]);
            Notification::make()->title('Cấp quyền chưa thành công')->body($e->getMessage())->danger()->send();
        }
        return redirect(SalesChannelResource::getUrl('edit', ['record' => $channel]));
    }
}
