<?php
namespace App\Http\Middleware;

use App\Http\Controllers\ShopController;
use App\Models\Category;
use App\Models\Product;
use App\Models\RefClick;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\View;

class ShareLayout
{
    public function handle(Request $request, Closure $next)
    {
        $ref = ShopController::cleanRef($request->query('ref'));
        if ($ref && $request->isMethod('GET')) {
            RefClick::create(['ref_code' => $ref, 'landing_path' => mb_substr($request->getRequestUri(), 0, 500)]);
            Cookie::queue('ysnt_ref', $ref, 60 * 24 * 30);
        }
        $cats = Category::where('is_active', true)
            ->withCount(['products' => fn ($q) => $q->where('status', 'active')])
            ->orderBy('sort_order')->get()->filter(fn ($c) => $c->products_count > 0)->values();
        View::share([
            'navCats' => $cats,
            'navTotal' => Product::where('status', 'active')->count(),
            'refCode' => $ref ?: ShopController::cleanRef($request->cookie('ysnt_ref')),
            'assetV' => max(@filemtime(public_path('js/site.js')) ?: 1, @filemtime(public_path('css/site.css')) ?: 1),
        ]);
        return $next($request);
    }
}
