<?php
namespace App\Support;

use App\Jobs\RunAddon;
use App\Meta\Meta;
use App\Models\AppSetting;
use App\Models\Order;

/** Hooks every add-on onto the one order table, so a new connection never needs its own copy of the data. */
class Addons
{
    public static function orderCreated(Order $o): void
    {
        if (in_array($o->channel, ['web', 'phone', 'corporate'], true)) RunAddon::dispatch('order_mail', $o->id)->afterCommit();
        if ($o->channel === 'web' && ($r = request())) {
            RunAddon::dispatch('meta_purchase', $o->id, ['ip' => $r->ip(), 'ua' => mb_substr((string) $r->userAgent(), 0, 400), 'url' => $r->headers->get('referer'),
                'fbp' => $r->cookie('_fbp'), 'fbc' => $r->cookie('_fbc')])->afterCommit();
        }
    }

    public static function run(string $task, Order $o, array $ctx): void
    {
        match ($task) {
            'order_mail' => self::orderMail($o),
            'meta_purchase' => Meta::purchase($o, $ctx),
            default => null,
        };
    }

    private static function orderMail(Order $o): void
    {
        $o->loadMissing(['items', 'customer']);
        $subject = 'Đơn hàng ' . $o->code . ' · ' . Company::get('brand_name');
        if ($to = $o->customer?->email) Mailer::send($to, $subject, view('emails.order', ['o' => $o, 'internal' => false])->render(), 'order_customer', $o->code);
        foreach (array_filter(array_map('trim', explode(',', (string) AppSetting::get('mail_order_to')))) as $to) {
            Mailer::send($to, '[Đơn mới] ' . $subject, view('emails.order', ['o' => $o, 'internal' => true])->render(), 'order_internal', $o->code);
        }
    }
}
