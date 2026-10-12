<?php
namespace App\Ai;

use App\Http\Controllers\SiteController;
use App\Inbox\Inbox;
use App\Models\AppSetting;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\ProductVariant;
use App\Support\Company;
use Illuminate\Support\Facades\Cache;

/** Sales advisor (plan section 12): asks who / occasion / budget, suggests from the live catalogue, hands over to staff. */
class Advisor
{
    public const DEFAULT_PROMPT = <<<'TXT'
Bạn là trợ lý AI tư vấn bán hàng của {brand}. Xưng "em", gọi khách là "anh/chị", giọng thân thiện, ngắn gọn (dưới 120 từ mỗi lượt), không dùng bảng hay tiêu đề.
Ở lượt đầu, chào và nói rõ mình là trợ lý AI, khách có thể gặp nhân viên bất cứ lúc nào.

Cách tư vấn:
- Cần biết đủ 3 điều: mua cho ai, dịp gì, ngân sách khoảng bao nhiêu. Hỏi tự nhiên, không hỏi lại điều khách đã nói.
- Đủ thông tin thì gợi ý tối đa 3 món trong DANH MỤC bên dưới, mỗi món một dòng kèm lý do dễ hiểu (ví dụ "đã làm sạch, chỉ việc chưng", "mở nắp là dùng"), cuối dòng ghi mã [[SP:id]] với id là số ở đầu dòng danh mục.
- Gợi ý thêm khi có lý do: quy cách lớn hơn nếu giá mỗi gram lợi hơn, thêm thiệp viết tay khi khách mua làm quà.
- Đặt hàng: khách bấm "Thêm vào giỏ" ở thẻ sản phẩm rồi thanh toán trên web, hoặc gọi hotline.

Rào chắn bắt buộc:
- Chỉ dùng sản phẩm, giá, tình trạng còn hàng có trong DANH MỤC. Không bịa khuyến mãi, quà tặng, thời gian giao hay phí giao.
- Không nói sản phẩm chữa được bệnh hay thay thế thuốc. Chỉ nói về nguồn gốc, cách dùng và chứng nhận đã có.
- Không tự giảm giá, không hứa ngoài chính sách.
- Khách nhắc tới công ty, số lượng lớn, quà cho nhân viên hay đối tác: hỏi số lượng, ngân sách mỗi phần quà, ngày giao, có in logo không, giao một nơi hay tới từng người nhận; rồi thêm [[B2B]].
- Khách muốn làm đại lý, cộng tác viên: hỏi khu vực và kênh bán, thêm [[DAI_LY]].
- Khiếu nại, đơn lỗi, khách bực bội, khách muốn gặp người thật, hoặc câu hỏi em không chắc: xin lỗi ngắn gọn, nói nhân viên sẽ trả lời ngay, thêm [[NHAN_VIEN]].
- Khách viết tiếng Trung, tiếng Hàn hoặc tiếng Anh thì trả lời bằng đúng ngôn ngữ đó.
TXT;

    public const MARKERS = ['B2B' => 'b2b', 'DAI_LY' => 'agent', 'NHAN_VIEN' => null, 'KHIEU_NAI' => 'complaint'];

    public static function catalogue(): string
    {
        return Cache::remember('ai_catalogue', 600, function () {
            return ProductVariant::with('product.category')->whereHas('product', fn ($q) => $q->where('status', 'active'))->orderBy('product_id')->orderBy('sort_order')->get()
                ->map(fn ($v) => $v->id . ' | ' . $v->product->name . ($v->name && $v->name !== 'Tiêu chuẩn' ? ' – ' . $v->name : '') . ' | ' . number_format((int) $v->list_price, 0, ',', '.') . 'đ | '
                    . ($v->is_available ? 'còn hàng' : 'tạm hết') . ' | ' . ($v->product->category?->name ?? ''))->implode("\n");
        });
    }

    /** stable text first (prompt, company, catalogue) so the provider can cache it */
    public static function system(): string
    {
        $prompt = AppSetting::get('ai_advisor_prompt') ?: self::DEFAULT_PROMPT;
        return str_replace('{brand}', Company::get('brand_name'), $prompt) . "\n\nTHÔNG TIN CÔNG TY\n"
            . 'Tên: ' . Company::get('company_name') . "\nHotline: " . Company::get('hotline_sales') . "\nNhà yến và showroom: " . Company::get('showroom_address')
            . "\nWeb: " . url('/') . "\nNhà máy riêng đạt chứng nhận FDA tại KCN Diên Phú, Khánh Hòa. Giao hàng toàn quốc, phí giao báo khi nhân viên xác nhận đơn.\n\n"
            . "DANH MỤC (id | sản phẩm – quy cách | giá | tình trạng | nhóm)\n" . self::catalogue();
    }

    /** @return array<int,array{role:string,content:string}> */
    public static function history(Conversation $c, int $limit = 20): array
    {
        return $c->messages()->where('sender', '!=', 'system')->latest('id')->take($limit)->get()->reverse()
            ->map(fn (ConversationMessage $m) => ['role' => $m->direction === 'in' ? 'user' : 'assistant', 'content' => $m->body])->values()->all();
    }

    /** answer the latest customer message; null when AI is not connected or failed (staff will answer) */
    public static function reply(Conversation $c): ?ConversationMessage
    {
        $res = Ai::chat('advisor', 'advisor', self::system(), self::history($c), ['effort' => 'low', 'timeout' => 45, 'conversation_id' => $c->id]);
        if ($res['status'] !== 'ok' || $res['text'] === '') { $c->update(['needs_human' => true]); return null; }
        [$text, $cards, $flags] = self::parse($res['text']);
        if ($flags) $c->update(['needs_human' => true] + (($t = collect($flags)->filter()->first()) ? ['tag' => $t] : []));
        return Inbox::send($c, $text, 'ai', $cards);
    }

    /** strip [[...]] markers; product markers become cards */
    public static function parse(string $text): array
    {
        $cards = []; $flags = [];
        $text = preg_replace_callback('/\[\[\s*([A-Z0-9_]+)(?::\s*(\d+))?\s*\]\]/', function ($m) use (&$cards, &$flags) {
            if ($m[1] === 'SP' && isset($m[2])) { $cards[(int) $m[2]] = true; return ''; }
            if (array_key_exists($m[1], self::MARKERS)) $flags[] = self::MARKERS[$m[1]];
            return '';
        }, $text);
        $variants = $cards ? ProductVariant::with('product')->whereIn('id', array_keys($cards))->get() : collect();
        $cards = $variants->filter(fn ($v) => $v->product?->status === 'active')->map(fn ($v) => ['type' => 'product', 'id' => $v->id, 'name' => $v->product->name,
            'variant' => $v->name === 'Tiêu chuẩn' ? '' : $v->name, 'price' => (int) $v->list_price, 'ok' => (bool) $v->is_available,
            'url' => url('/san-pham/' . $v->product->slug . '?qc=' . $v->id), 'img' => SiteController::img($v->product->image)])->values()->all();
        return [trim(preg_replace("/[ \t]+\n/", "\n", preg_replace('/\n{3,}/', "\n\n", $text))), $cards, $flags];
    }
}
