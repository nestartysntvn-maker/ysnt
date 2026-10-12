<?php
namespace App\Channels\Connectors;

use App\Channels\Connector;
use App\Channels\NotConfigured;
use App\Models\SalesChannel;

/** Shared plumbing of the three marketplace connectors: credentials, OAuth, token lifetime. */
abstract class Marketplace implements Connector
{
    /** URL the seller opens to grant our app access to the shop */
    abstract public function authorizeUrl(SalesChannel $c, string $redirect, string $state): string;
    /** OAuth callback: trade the code for tokens and store them on the channel */
    abstract public function exchange(SalesChannel $c, array $query): void;
    abstract public function refresh(SalesChannel $c): void;
    /** one cheap read call; returns a human sentence (shop name) */
    abstract public function ping(SalesChannel $c): string;

    public static function make(string $platform): ?self
    {
        return match ($platform) { 'tiktok' => new TikTokShop(), 'shopee' => new Shopee(), 'lazada' => new Lazada(), default => null };
    }

    public static function hasApp(SalesChannel $c): bool { $cr = $c->credentials ?? []; return ! empty($cr['app_key']) && ! empty($cr['app_secret']); }
    public static function ready(SalesChannel $c): bool { return self::hasApp($c) && ! empty(($c->credentials ?? [])['access_token']); }

    public function needsRefresh(SalesChannel $c): bool
    {
        $cr = $c->credentials ?? [];
        return ! empty($cr['refresh_token']) && (empty($cr['access_expires_at']) || (int) $cr['access_expires_at'] < time() + 3600);
    }

    protected function cred(SalesChannel $c, string $k): string
    {
        $v = (string) (($c->credentials ?? [])[$k] ?? '');
        if ($v === '') throw new NotConfigured('Gian hàng ' . $c->name . ' chưa có ' . $k . '.');
        return $v;
    }

    protected function store(SalesChannel $c, array $kv): void
    {
        $c->credentials = array_merge($c->credentials ?? [], array_filter($kv, fn ($v) => $v !== null && $v !== ''));
        $c->token_expires_at = isset($kv['access_expires_at']) ? \Illuminate\Support\Carbon::createFromTimestamp((int) $kv['access_expires_at']) : $c->token_expires_at;
        $c->save();
    }

    /** group per-unit order lines (TikTok, Lazada return one line per unit) by platform sku */
    protected static function group(array $lines): array
    {
        $out = [];
        foreach ($lines as $l) {
            $k = ($l['sku_id'] ?? '') . '|' . $l['unit_price'];
            if (isset($out[$k])) $out[$k]['qty'] += $l['qty']; else $out[$k] = $l;
        }
        return array_values($out);
    }
}
