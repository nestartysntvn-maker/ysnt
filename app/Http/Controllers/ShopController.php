<?php
namespace App\Http\Controllers;

use App\Models\Ambassador;
use App\Models\Product;
use App\Models\RefClick;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function index(Request $request)
    {
        $ref = $this->cleanRef($request->query('ref'));
        $response = null;
        if ($ref) {
            RefClick::create(['ref_code' => $ref, 'landing_path' => mb_substr($request->getRequestUri(), 0, 500)]);
        }
        $products = Product::with(['variants', 'brand', 'category'])
            ->where('status', 'active')->orderBy('category_id')->orderBy('id')->get();
        $data = $products->map(fn ($p) => [
            'slug' => $p->slug,
            'name' => $p->name,
            'cat' => $p->category?->name ?? 'Khác',
            'brand' => $p->brand?->name,
            'img' => $p->image ? (str_starts_with($p->image, '//') ? 'https:' . $p->image : $p->image) : '',
            'v' => $p->variants->map(fn ($v) => [
                'id' => $v->id, 'n' => $v->name, 'sku' => $v->sku, 'p' => (int) $v->list_price, 'ok' => (bool) $v->is_available,
            ])->values(),
        ])->values();
        $cats = $data->pluck('cat')->unique()->values();
        $response = response()->view('shop', ['data' => $data, 'cats' => $cats, 'ref' => $ref ?: $this->cleanRef($request->cookie('ysnt_ref'))]);
        if ($ref) {
            $response->cookie('ysnt_ref', $ref, 60 * 24 * 30);
        }
        return $response;
    }

    public function refInfo(string $code)
    {
        $code = $this->cleanRef($code);
        $amb = $code ? Ambassador::where('ref_code', $code)->where('status', 'active')->first() : null;
        if (! $amb) {
            return response()->json(['valid' => false]);
        }
        $policy = $amb->policy();
        return response()->json([
            'valid' => true,
            'discount_percent' => $policy?->customer_discount_percent !== null ? (float) $policy->customer_discount_percent : 0,
        ]);
    }

    public static function cleanRef(?string $ref): ?string
    {
        $ref = strtoupper(trim((string) $ref));
        return preg_match('/^[A-Z0-9_-]{2,32}$/', $ref) ? $ref : null;
    }
}
