<?php
namespace App\Providers;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // settings are read many times per page; load the table once per request / queued job
        $this->app->scoped('app_settings.memo', function () {
            try { return new \ArrayObject(\App\Models\AppSetting::query()->pluck('value', 'key')->all()); } catch (\Throwable) { return new \ArrayObject(); }
        });
    }

    public function boot(): void
    {
        // every route gets its own counter; the plain "throttle:n,m" form shares one counter per IP across all routes
        $own = fn (string $name, int $per, int $minutes = 1) => \Illuminate\Support\Facades\RateLimiter::for($name,
            fn (\Illuminate\Http\Request $r) => \Illuminate\Cache\RateLimiting\Limit::perMinutes($minutes, $per)->by($name . '|' . $r->ip()));
        $own('track', 20); $own('variants', 60); $own('ref', 30); $own('orders', 10);
        $own('chat-poll', 60); $own('chat-send', 20); $own('chat-contact', 5); $own('webhooks', 300);
        $own('apply', 5, 10); $own('onboard', 10, 10); $own('wp-login', 20); $own('wp-otp-send', 5); $own('wp-otp-verify', 10); $own('wp-pass', 10); $own('wp-refer', 10, 10);

        $models = ['Product', 'ProductVariant', 'Order', 'CommissionPolicy', 'Ambassador', 'Commission', 'PayoutRequest', 'User', 'Category', 'Customer'];
        foreach ($models as $m) {
            $class = 'App\\Models\\' . $m;
            foreach (['created', 'updated', 'deleted'] as $event) {
                $class::$event(function (Model $model) use ($m, $event) {
                    if (! auth()->check()) return; // log staff actions; web checkout is logged via orders itself
                    $hidden = ['password', 'remember_token', 'bank_info', 'updated_at', 'created_at'];
                    if ($event === 'updated') {
                        $changes = collect($model->getChanges())->except($hidden)
                            ->mapWithKeys(fn ($new, $k) => [$k => ['old' => mb_strimwidth((string) $model->getOriginal($k), 0, 120, '…'), 'new' => mb_strimwidth((string) $new, 0, 120, '…')]])->all();
                        if (! $changes) return;
                    } else {
                        $changes = collect($model->getAttributes())->except($hidden)->map(fn ($v) => mb_strimwidth((string) $v, 0, 120, '…'))->all();
                    }
                    ActivityLog::create(['user_id' => auth()->id(), 'action' => $event, 'subject_type' => $m, 'subject_id' => $model->getKey(),
                        'changes' => $changes, 'ip' => request()?->ip()]);
                });
            }
        }
    }
}
