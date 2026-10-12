<?php
namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Storage;

/** One company profile + brand kit. Web, POS bill, e-mail, JSON-LD and the AI advisor all read from here. */
class Company
{
    /** what the site showed before the profile page existed; an empty field falls back to these */
    public const DEFAULTS = [
        'company_name' => 'Công ty Cổ phần Yến Sào Nha Trang',
        'brand_name' => 'Yến Sào Nha Trang',
        'brand_tagline' => 'Từ nhà yến Khánh Hòa',
        'hq_address' => '159 Phong Châu, Phường Phước Hải, Nha Trang, Khánh Hòa',
        'tax_code' => '4201467218',
        'tax_issued' => 'cấp ngày 18/04/2012 tại Sở Kế hoạch và Đầu tư Khánh Hòa',
        'showroom_address' => '01 Nguyễn Thiện Thuật, Nha Trang',
        'hotline_sales' => '1800 64 68 80',
        'email_public' => 'info@ysnt.com.vn',
    ];
    public const SOCIALS = ['social_facebook' => 'Facebook', 'social_instagram' => 'Instagram', 'social_threads' => 'Threads', 'social_tiktok' => 'TikTok',
        'social_youtube' => 'YouTube', 'social_zalo' => 'Zalo OA', 'social_shopee' => 'Shopee', 'social_lazada' => 'Lazada'];

    public static function get(string $k): string { return (string) (AppSetting::get($k) ?? self::DEFAULTS[$k] ?? ''); }
    public static function tel(): string { return preg_replace('/\D/', '', self::get('hotline_sales')); }
    public static function logoUrl(): ?string { $p = AppSetting::get('brand_logo'); return $p ? Storage::disk('public')->url($p) : null; }
    public static function color(): ?string { $c = (string) AppSetting::get('brand_color'); return preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? $c : null; }
    public static function mapsUrl(): string { return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode(self::get('showroom_address')); }

    /** @return array<string,string> label => url, only the ones filled in */
    public static function socials(): array
    {
        $out = [];
        foreach (self::SOCIALS as $k => $label) if ($u = AppSetting::get($k)) $out[$label] = $u;
        return $out;
    }

    /** schema.org Organization + Store for the whole site */
    public static function jsonLd(): array
    {
        $org = ['@context' => 'https://schema.org', '@type' => 'Organization', '@id' => url('/#org'), 'name' => self::get('company_name'), 'alternateName' => self::get('brand_name'),
            'url' => url('/'), 'telephone' => self::get('hotline_sales'), 'email' => self::get('email_public'), 'taxID' => self::get('tax_code'),
            'address' => ['@type' => 'PostalAddress', 'streetAddress' => self::get('hq_address'), 'addressCountry' => 'VN']];
        if ($l = self::logoUrl()) $org['logo'] = $l;
        if ($s = array_values(self::socials())) $org['sameAs'] = $s;
        $store = ['@context' => 'https://schema.org', '@type' => 'Store', 'name' => self::get('brand_name') . ' · Nhà yến và showroom', 'parentOrganization' => ['@id' => url('/#org')],
            'telephone' => self::get('hotline_sales'), 'url' => url('/tham-quan'),
            'address' => ['@type' => 'PostalAddress', 'streetAddress' => self::get('showroom_address'), 'addressLocality' => 'Nha Trang', 'addressRegion' => 'Khánh Hòa', 'addressCountry' => 'VN']];
        if ($g = AppSetting::get('seo_gbp_url')) $store['hasMap'] = $g;
        return [$org, $store];
    }
}
