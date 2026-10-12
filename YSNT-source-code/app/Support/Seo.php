<?php
namespace App\Support;

use App\Http\Controllers\SiteController;
use App\Models\AppSetting;
use App\Models\Category;
use App\Models\Product;
use App\Models\SeoMeta;

/** Per-page SEO overrides stored in seo_meta; product/category keyed by id so a slug change keeps them. */
class Seo
{
    public const PAGES = ['/' => 'Trang chủ', '/san-pham' => 'Tất cả sản phẩm', '/tham-quan' => 'Tham quan nhà yến', '/gioi-thieu' => 'Giới thiệu', '/lien-he' => 'Liên hệ', '/tuyen-dung' => 'Tuyển dụng'];
    public const TITLE_MAX = 60;
    public const DESC_MAX = 155;

    public static function indexable(): bool { return AppSetting::enabled('seo_allow_index'); }

    /** merge an override into the view data of a page */
    public static function apply(array $data, string $type, string $key): array
    {
        $m = SeoMeta::for($type, $key);
        if (! $m) return $data;
        if ($m->meta_title) $data['title'] = $m->meta_title;
        if ($m->meta_description) $data['desc'] = $m->meta_description;
        if ($m->og_image) $data['ogImage'] = $m->og_image;
        if ($m->noindex) $data['noindex'] = true;
        return $data;
    }

    /** what the page shows by default + the text the AI reads; null when the page no longer exists */
    public static function info(string $type, string $key): ?array
    {
        $plain = fn (?string $h, int $n = 1500) => mb_substr(trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string) $h)))), 0, $n);
        if ($type === 'product') {
            $p = Product::with(['variants', 'category', 'brand'])->find((int) $key);
            if (! $p) return null;
            $prices = $p->variants->pluck('list_price');
            return ['name' => $p->name, 'url' => url('/san-pham/' . $p->slug), 'title' => $p->name . ' · ' . Company::get('brand_name'), 'desc' => $plain($p->description, 160),
                'text' => "Sản phẩm: {$p->name}\nNhãn: " . ($p->brand?->name ?? '') . "\nNhóm: " . ($p->category?->name ?? '') . "\nQuy cách: " . $p->variants->pluck('name')->implode(', ')
                    . "\nGiá: " . number_format((int) $prices->min(), 0, ',', '.') . 'đ' . ($prices->max() > $prices->min() ? ' đến ' . number_format((int) $prices->max(), 0, ',', '.') . 'đ' : '')
                    . "\nMô tả: " . $plain($p->description), 'img' => SiteController::img($p->image)];
        }
        if ($type === 'category') {
            $c = Category::withCount('products')->find((int) $key);
            if (! $c) return null;
            return ['name' => $c->name, 'url' => url('/danh-muc/' . $c->slug), 'title' => $c->name . ' · ' . Company::get('brand_name'), 'desc' => '',
                'text' => "Danh mục: {$c->name}\nSố sản phẩm: {$c->products_count}\nSản phẩm: " . Product::where('category_id', $c->id)->where('status', 'active')->pluck('name')->implode(', ')];
        }
        if (! isset(self::PAGES[$key])) return null;
        return ['name' => self::PAGES[$key], 'url' => url($key), 'title' => self::PAGES[$key] . ' · ' . Company::get('brand_name'), 'desc' => '',
            'text' => 'Trang: ' . self::PAGES[$key] . "\nCông ty: " . Company::get('company_name') . "\nNhà yến và showroom: " . Company::get('showroom_address')];
    }

    /** add an empty row for every page that has none yet; returns how many were added */
    public static function sync(): int
    {
        $want = collect(self::PAGES)->keys()->map(fn ($k) => ['page', $k])
            ->concat(Category::pluck('id')->map(fn ($id) => ['category', (string) $id]))
            ->concat(Product::where('status', 'active')->pluck('id')->map(fn ($id) => ['product', (string) $id]));
        $have = SeoMeta::get(['page_type', 'page_key'])->map(fn ($m) => $m->page_type . ':' . $m->page_key)->flip();
        $n = 0;
        foreach ($want as [$t, $k]) if (! isset($have["$t:$k"])) { SeoMeta::create(['page_type' => $t, 'page_key' => $k]); $n++; }
        return $n;
    }
}
