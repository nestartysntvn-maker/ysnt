<?php
namespace App\Ai;

use App\Models\Conversation;
use App\Support\Company;
use App\Support\Seo;

/** AI that helps staff: draft replies in the inbox, SEO titles and descriptions. Uses the "staff" provider. */
class Assist
{
    public static function suggestReply(Conversation $c): array
    {
        $system = Advisor::system() . "\n\nNHIỆM VỤ LẦN NÀY: soạn NHÁP câu trả lời để nhân viên chăm sóc khách xem rồi gửi. Viết đúng lời gửi khách, xưng em. Không chèn mã [[...]]; nếu gợi ý sản phẩm thì ghi tên và giá.";
        return Ai::chat('reply_suggest', 'staff', $system, Advisor::history($c), ['effort' => 'low', 'timeout' => 45, 'conversation_id' => $c->id]);
    }

    /** @return array{status:string,data:?array{title:string,description:string,keyword:string},error:?string} */
    public static function seo(string $type, string $key): array
    {
        $info = Seo::info($type, $key);
        if (! $info) return ['status' => 'error', 'data' => null, 'error' => 'Trang không còn tồn tại'];
        $system = 'Bạn là chuyên viên SEO cho web bán yến sào của ' . Company::get('brand_name') . ' (Nha Trang, Khánh Hòa). Viết tiếng Việt có dấu, tự nhiên, đúng sự thật trên trang, '
            . 'không nói chữa bệnh, không hứa khuyến mãi. Trả về JSON đúng 3 khóa: "title" (tối đa ' . Seo::TITLE_MAX . ' ký tự, có từ khóa chính ở đầu, kết thúc bằng " · ' . Company::get('brand_name') . '" nếu còn chỗ), '
            . '"description" (120 đến ' . Seo::DESC_MAX . ' ký tự, có lời mời hành động), "keyword" (từ khóa chính người Việt hay tìm, 2 đến 5 chữ).';
        $schema = ['type' => 'object', 'properties' => ['title' => ['type' => 'string'], 'description' => ['type' => 'string'], 'keyword' => ['type' => 'string']],
            'required' => ['title', 'description', 'keyword'], 'additionalProperties' => false];
        $res = Ai::chat('seo', 'staff', $system, [['role' => 'user', 'content' => "Địa chỉ trang: {$info['url']}\n{$info['text']}"]], ['effort' => 'medium', 'schema' => $schema]);
        if ($res['status'] !== 'ok') return ['status' => $res['status'], 'data' => null, 'error' => $res['error']];
        $j = Ai::json($res['text']);
        if (! $j || empty($j['title']) || empty($j['description'])) return ['status' => 'error', 'data' => null, 'error' => 'AI trả về sai định dạng'];
        return ['status' => 'ok', 'data' => ['title' => mb_substr(trim($j['title']), 0, 70), 'description' => mb_substr(trim($j['description']), 0, 300), 'keyword' => mb_substr(trim((string) ($j['keyword'] ?? '')), 0, 100)], 'error' => null];
    }
}
