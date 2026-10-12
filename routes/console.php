<?php
use Illuminate\Support\Facades\Schedule;
Schedule::command('channels:sync')->everyTenMinutes()->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=720')->daily();
Schedule::call(function () { if (\App\Models\AppSetting::get('zalo_refresh_token') && now()->addMinutes(30)->gte(\Illuminate\Support\Carbon::parse(\App\Models\AppSetting::get('zalo_token_expires_at', now()->toIso8601String())))) \App\Zalo\Zns::refreshToken(); })->everyFifteenMinutes()->name('zalo_refresh');
Schedule::call(function () { foreach (\App\Models\SalesChannel::whereIn('platform', ['tiktok', 'shopee', 'lazada'])->get() as $c) \App\Channels\ChannelSync::refreshToken($c); })->everyThirtyMinutes()->name('channel_tokens')->withoutOverlapping();
Schedule::call(function () {
    foreach (\App\Models\SalesChannel::where('platform', 'tiktok')->where('status', 'active')->get() as $c) {
        if (empty(($c->credentials ?? [])['sync_chat']) || ! \App\Channels\Connectors\Marketplace::ready($c)) continue;
        try { (new \App\Channels\Connectors\TikTokShop())->pollChats($c); } catch (\Throwable $e) { \App\Models\ChannelSyncLog::create(['sales_channel_id' => $c->id, 'kind' => 'chat', 'status' => 'error', 'message' => mb_substr($e->getMessage(), 0, 2000)]); }
    }
})->everyTwoMinutes()->name('tiktok_chat')->withoutOverlapping();
Schedule::call(fn () => \App\Hr\Tasks::sweep())->everyFifteenMinutes()->name('task_reminders')->withoutOverlapping();
