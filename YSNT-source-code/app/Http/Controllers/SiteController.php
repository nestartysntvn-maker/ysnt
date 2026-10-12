<?php
namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SeoMeta;
use App\Support\Company;
use App\Support\Seo;
use Illuminate\Http\Request;

class SiteController extends Controller
{
    private const GRAM_CATS = ['Tổ yến tinh chế', 'Tổ yến nguyên tổ'];
    private const DIM1 = ['Tổ yến ăn liền' => 'Vị', 'Tổ yến chưng sẵn' => 'Vị', 'Sữa chua sấy khô' => 'Vị', 'Nước yến thật' => 'Vị', 'Súp tổ yến' => 'Vị', 'Bột tổ yến' => 'Vị', 'Chè tổ yến' => 'Vị', 'Mặt nạ yến' => 'Loại', 'Tinh chất yến' => 'Loại'];

    private function q() { return Product::with(['variants', 'brand', 'category'])->where('status', 'active'); }
    public static function img(?string $s): ?string { return $s ? (str_starts_with($s, '//') ? 'https:' . $s : $s) : null; }

    public function home()
    {
        $all = $this->q()->orderBy('id')->get();
        $homeData = $all->map(fn ($p) => ['slug' => $p->slug, 'name' => $p->name, 'cat' => $p->category?->name, 'img' => self::img($p->image),
            'v' => $p->variants->map(fn ($v) => ['id' => $v->id, 'n' => $v->name, 'p' => (int) $v->list_price, 'ok' => $v->is_available])->values()])->values();
        $tiles = $all->groupBy(fn ($p) => $p->category?->slug)->map(fn ($g) => ['slug' => $g->first()->category?->slug, 'name' => $g->first()->category?->name,
            'img' => self::img($g->first()->image), 'count' => $g->count(), 'min' => $g->flatMap->variants->min('list_price')])
            ->filter(fn ($t) => $t['slug'] && ! in_array($t['name'], ['Khác', 'Combo']))->sortByDesc('count')->take(8)->values();
        $bestSlugs = ['to-yen-an-lien', 'to-yen-tinh-che-yen-sao-nha-trang', 'to-yen-chung-san', 'sua-chua-to-yen-say-kho', 'to-yen-tinh-che-nest-art', 'sup-to-yen', 'nuoc-yen-that', 'to-yen-nguyen-to'];
        $best = $all->whereIn('slug', $bestSlugs)->sortBy(fn ($p) => array_search($p->slug, $bestSlugs))->values();
        $gifts = $all->filter(fn ($p) => $p->category?->slug === 'bo-qua-tang')->sortByDesc(fn ($p) => $p->variants->where('is_available', true)->count())->take(4);
        return view('home', Seo::apply(['homeData' => $homeData, 'tiles' => $tiles, 'best' => $best, 'gifts' => $gifts,
            'title' => 'Yến Sào Nha Trang · Tổ yến từ nhà yến Khánh Hòa', 'canonical' => url('/')], 'page', '/'));
    }

    public function listing(Request $r, ?string $slug = null)
    {
        $cat = $slug ? Category::where('slug', $slug)->firstOrFail() : null;
        $q = $r->routeIs('search') ? trim((string) $r->query('q')) : null;
        $brand = $r->query('nhan');
        $sort = $r->query('sx');
        $query = $this->q();
        if ($cat) $query->where('category_id', $cat->id);
        if ($brand) $query->whereHas('brand', fn ($b) => $b->where('slug', $brand));
        if ($q !== null && $q !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
            $query->where(fn ($w) => $w->where('name', 'like', $like)->orWhereHas('variants', fn ($v) => $v->where('name', 'like', $like)->orWhere('sku', 'like', $like))
                ->orWhereHas('category', fn ($c) => $c->where('name', 'like', $like)));
        }
        $products = $query->orderBy('category_id')->orderBy('id')->get();
        $minp = fn ($p) => $p->variants->min('list_price');
        $products = (match ($sort) { 'gia-tang' => $products->sortBy($minp), 'gia-giam' => $products->sortByDesc($minp), 'ten' => $products->sortBy('name'), default => $products })->values();
        $title = $cat ? $cat->name : ($q !== null ? "Tìm “{$q}”" : 'Tất cả sản phẩm');
        $data = ['cat' => $cat, 'q' => $q, 'brand' => $brand, 'sort' => $sort, 'products' => $products,
            'brands' => Brand::orderBy('name')->get(), 'title' => $title . ' · Yến Sào Nha Trang', 'canonical' => $cat ? url('/danh-muc/' . $cat->slug) : url('/san-pham')];
        if ($q !== null) return view('listing', $data + ['noindex' => true]);
        if ($cat) $data['jsonLd'] = [self::crumbs([[$cat->name, url('/danh-muc/' . $cat->slug)]])];
        return view('listing', $cat ? Seo::apply($data, 'category', (string) $cat->id) : Seo::apply($data, 'page', '/san-pham'));
    }

    public function product(Request $r, string $slug)
    {
        $p = $this->q()->where('slug', $slug)->firstOrFail();
        $start = (int) $r->query('qc');
        $blocks = []; $idx = [];
        foreach ($p->variants as $v) {
            $h = $v->description ?: self::clean($p->description);
            if (! $h) { $idx[$v->id] = null; continue; }
            $k = md5($h); if (! isset($blocks[$k])) $blocks[$k] = $h;
            $idx[$v->id] = array_search($k, array_keys($blocks));
        }
        $pd = ['start' => $start, 'gram' => in_array($p->category?->name, self::GRAM_CATS), 'dim1' => self::DIM1[$p->category?->name] ?? 'Quy cách',
            'v' => $p->variants->map(fn ($v) => ['id' => $v->id, 'n' => $v->name, 'sku' => $v->sku, 'p' => (int) $v->list_price, 'ok' => $v->is_available, 'd' => $idx[$v->id]])->values()];
        $related = $this->q()->where('category_id', $p->category_id)->where('id', '!=', $p->id)->take(4)->get();
        if ($related->count() < 4) $related = $related->concat($this->q()->where('category_id', '!=', $p->category_id)->inRandomOrder()->take(4 - $related->count())->get());
        $img = self::img($p->image);
        $prices = $p->variants->pluck('list_price')->map(fn ($v) => (int) $v);
        $ld = ['@context' => 'https://schema.org', '@type' => 'Product', 'name' => $p->name, 'url' => url('/san-pham/' . $p->slug),
            'brand' => ['@type' => 'Brand', 'name' => $p->brand?->name ?: Company::get('brand_name')], 'category' => $p->category?->name,
            'description' => mb_substr(trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string) $p->description)))), 0, 500) ?: null,
            'sku' => $p->variants->first()?->sku, 'offers' => ['@type' => 'AggregateOffer', 'priceCurrency' => 'VND', 'lowPrice' => $prices->min(), 'highPrice' => $prices->max(),
                'offerCount' => $prices->count(), 'availability' => 'https://schema.org/' . ($p->variants->contains('is_available', true) ? 'InStock' : 'OutOfStock'),
                'seller' => ['@id' => url('/#org')]]];
        if ($img) $ld['image'] = [$img];
        $crumbs = $p->category ? [[$p->category->name, url('/danh-muc/' . $p->category->slug)], [$p->name, url('/san-pham/' . $p->slug)]] : [[$p->name, url('/san-pham/' . $p->slug)]];
        return view('product', Seo::apply(['p' => $p, 'pd' => $pd, 'img' => $img, 'related' => $related, 'descBlocks' => array_values($blocks),
            'title' => $p->name . ' · Yến Sào Nha Trang', 'desc' => mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags((string) $p->description))), 0, 160) ?: null,
            'canonical' => url('/san-pham/' . $p->slug), 'ogImage' => $img, 'ogType' => 'product', 'bodyClass' => 'has-buy', 'jsonLd' => [array_filter($ld, fn ($v) => $v !== null), self::crumbs($crumbs)],
            'pixelEvent' => ['ViewContent', ['content_ids' => $p->variants->pluck('sku')->filter()->values(), 'content_type' => 'product', 'content_name' => $p->name, 'value' => $prices->min(), 'currency' => 'VND']]], 'product', (string) $p->id));
    }

    public static function clean(?string $html): ?string
    {
        if (! $html || trim(strip_tags($html)) === '') return null;
        $html = preg_replace('#<(script|style|iframe|object|embed|form)[^>]*>.*?</\1>#is', '', $html);
        $html = strip_tags($html, '<p><br><b><strong><i><em><ul><ol><li><h2><h3><h4><table><thead><tbody><tr><td><th><img><a><span>');
        $html = preg_replace('/\s(on\w+|style|class|id)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
        $html = preg_replace('/(href|src)\s*=\s*"\s*javascript:[^"]*"/i', '$1="#"', $html);
        $html = preg_replace('#src="//#', 'src="https://', $html);
        $html = preg_replace('#<img[^>]+src="[^"]*(\.svg|s\.w\.org|emoji)[^"]*"[^>]*>#i', '', $html);
        return $html;
    }

    /** schema.org BreadcrumbList, home first */
    public static function crumbs(array $items): array
    {
        $all = array_merge([['Trang chủ', url('/')]], $items);
        return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => array_map(fn ($it, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $it[0], 'item' => $it[1]], $all, array_keys($all))];
    }

    public function cart() { return view('shell', ['crumb' => 'Giỏ hàng', 'heading' => 'Giỏ hàng', 'rootId' => 'cart-root', 'title' => 'Giỏ hàng · Yến Sào Nha Trang']); }
    public function checkout() { return view('shell', ['crumb' => 'Thanh toán', 'heading' => 'Thanh toán', 'rootId' => 'co-root', 'title' => 'Thanh toán · Yến Sào Nha Trang']); }
    public function success() { return view('shell', ['crumb' => 'Đặt hàng thành công', 'heading' => '', 'rootId' => 'ok-root', 'title' => 'Đặt hàng thành công · Yến Sào Nha Trang']); }

    public function track(Request $r)
    {
        $ma = strtoupper(trim((string) $r->query('ma')));
        $sdt = preg_replace('/[\s.]/', '', (string) $r->query('sdt'));
        $order = null;
        if ($ma !== '' && $sdt !== '') {
            $order = Order::with('items')->where('code', $ma)->where('ship_phone', $sdt)->first();
        }
        return view('track', ['ma' => $ma, 'sdt' => $sdt, 'order' => $order, 'searched' => $ma !== '' && $sdt !== '', 'title' => 'Tra cứu đơn hàng · Yến Sào Nha Trang']);
    }

    public function variants(Request $r)
    {
        $ids = collect(explode(',', (string) $r->query('ids')))->map(fn ($x) => (int) $x)->filter()->take(50);
        return ProductVariant::with('product')->whereIn('id', $ids)->get()->map(fn ($v) => [
            'id' => $v->id, 'n' => $v->name, 'p' => (int) $v->list_price, 'ok' => $v->is_available && $v->product->status === 'active',
            'product' => $v->product->name, 'slug' => $v->product->slug, 'img' => self::img($v->product->image),
        ])->values();
    }

    private function content(string $file): string
    {
        $path = storage_path('app/content/' . $file . '.html');
        return is_file($path) ? file_get_contents($path) : '';
    }

    public function about() { return view('page', Seo::apply(['heading' => 'Giới thiệu', 'lead' => Company::get('company_name') . ', đơn vị đầu tiên đưa tổ yến ăn liền ra thị trường Việt Nam.', 'html' => $this->content('gioi-thieu'), 'title' => 'Giới thiệu · Yến Sào Nha Trang', 'canonical' => url('/gioi-thieu')], 'page', '/gioi-thieu')); }
    public function contact() { return view('page', Seo::apply(['heading' => 'Liên hệ', 'html' => $this->content('lien-he'), 'title' => 'Liên hệ · Yến Sào Nha Trang', 'canonical' => url('/lien-he'),
        'side' => '<h3 style="margin-top:0">Gọi miễn phí</h3><p><a class="btn btn-red" style="width:100%" href="tel:' . e(Company::tel()) . '">' . e(Company::get('hotline_sales')) . '</a></p><p class="note">Hỏi sản phẩm, đặt hàng số lượng lớn, quà tặng doanh nghiệp, hợp tác đại lý.</p>'], 'page', '/lien-he')); }
    public function visit()
    {
        $addr = e(Company::get('showroom_address'));
        return view('page', Seo::apply(['heading' => 'Tham quan nhà yến', 'lead' => Company::get('showroom_address') . '.', 'html' => $this->content('tham-quan'), 'title' => 'Tham quan nhà yến tại Nha Trang · Yến Sào Nha Trang', 'canonical' => url('/tham-quan'),
            'side' => '<h3 style="margin-top:0">Thông tin nhanh</h3><p><b>Địa chỉ</b><br>' . $addr . '</p><p><b>Giờ mở cửa, đặt lịch đoàn</b><br>Gọi hotline để hỏi</p><p><a class="btn btn-red" style="width:100%" href="tel:' . e(Company::tel()) . '">Gọi ' . e(Company::get('hotline_sales')) . '</a></p><p><a class="btn btn-line" style="width:100%" target="_blank" rel="noopener" href="' . e(\App\Models\AppSetting::get('seo_gbp_url') ?: Company::mapsUrl()) . '">Chỉ đường</a></p>'], 'page', '/tham-quan'));
    }

    public function policy(string $slug)
    {
        $map = ['van-chuyen' => 'Chính sách vận chuyển', 'thanh-toan' => 'Hình thức thanh toán', 'doi-tra' => 'Chính sách bảo hành đổi trả', 'bao-mat' => 'Chính sách bảo mật'];
        abort_unless(isset($map[$slug]), 404);
        $html = $this->content($slug);
        $html = preg_replace('#<h[1-4][^>]*>(?:\s|<[^>]+>|&nbsp;)*' . preg_quote($map[$slug], '#') . '(?:\s|<[^>]+>|&nbsp;)*</h[1-4]>#iu', '', $html, 1);
        return view('page', ['heading' => $map[$slug], 'html' => $html, 'title' => $map[$slug] . ' · Yến Sào Nha Trang', 'source' => 'ysnt.com.vn']);
    }

    public function sitemap()
    {
        $hidden = SeoMeta::where('noindex', true)->get(['page_type', 'page_key'])->map(fn ($m) => $m->page_type . ':' . $m->page_key)->flip();
        $urls = collect(['/', '/san-pham', '/tham-quan', '/gioi-thieu', '/lien-he', '/tuyen-dung', '/chinh-sach/van-chuyen', '/chinh-sach/thanh-toan', '/chinh-sach/doi-tra', '/chinh-sach/bao-mat'])
            ->reject(fn ($u) => isset($hidden['page:' . $u]))->map(fn ($u) => [$u, null])
            ->concat(Category::where('is_active', true)->get(['id', 'slug'])->reject(fn ($c) => isset($hidden['category:' . $c->id]))->map(fn ($c) => ['/danh-muc/' . $c->slug, null]))
            ->concat(Product::where('status', 'active')->get(['id', 'slug', 'updated_at'])->reject(fn ($p) => isset($hidden['product:' . $p->id]))->map(fn ($p) => ['/san-pham/' . $p->slug, $p->updated_at?->toDateString()]));
        $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            . $urls->map(fn ($u) => '<url><loc>' . e(url($u[0])) . '</loc>' . ($u[1] ? '<lastmod>' . $u[1] . '</lastmod>' : '') . '</url>')->implode('') . '</urlset>';
        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}

