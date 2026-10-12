<?php
namespace App\Filament\Pages;

use App\Ai\Advisor;
use App\Ai\Ai;
use App\Filament\SettingsPage;
use App\Models\AiLog;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;

class AiSettings extends SettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;
    protected static ?int $navigationSort = 6;
    protected static ?string $navigationLabel = 'AI';
    protected static ?string $title = 'AI tư vấn và hỗ trợ';
    protected static ?string $slug = 'ai';

    public function banner(): ?array
    {
        $m = AiLog::where('created_at', '>=', now()->startOfMonth())->selectRaw("provider, SUM(status='ok') ok, SUM(status='error') err, SUM(input_tokens) i, SUM(output_tokens) o")->groupBy('provider')->get();
        $use = $m->isEmpty() ? 'Tháng này chưa có lượt gọi AI.' : 'Tháng này: ' . $m->map(fn ($r) => (Ai::PROVIDERS[$r->provider] ?? $r->provider) . ' ' . (int) $r->ok . ' lượt'
            . ($r->err ? ', ' . (int) $r->err . ' lỗi' : '') . ', ' . number_format((int) $r->i) . ' token vào, ' . number_format((int) $r->o) . ' token ra')->implode(' · ');
        $state = collect(Ai::GROUPS)->map(fn ($l, $g) => "<b>$l:</b> " . Ai::PROVIDERS[Ai::provider($g)] . (Ai::ready($g) ? ' (đã kết nối)' : ' (chưa có khóa, chạy thử)'))->implode('<br>');
        return ['tone' => Ai::ready('advisor') ? 'ok' : 'info', 'html' => $state . '<br>' . e($use)];
    }

    public function sections(): array
    {
        $keys = [];
        foreach (Ai::PROVIDERS as $p => $label) {
            $keys["ai_key_$p"] = ['label' => "Khóa API $label", 'type' => 'secret'];
            $keys["ai_model_$p"] = ['label' => "Model $label", 'placeholder' => Ai::DEFAULT_MODELS[$p] ?: 'điền tên model khi có tài khoản', 'hint' => Ai::DEFAULT_MODELS[$p] ? 'Để trống là dùng ' . Ai::DEFAULT_MODELS[$p] : null];
        }
        return [
            'Chọn nhà cung cấp cho từng việc' => ['desc' => 'Đổi nhà cung cấp ở đây, không phải sửa code. Việc nào chưa có khóa thì chạy thử: không gọi AI, chuyển cho nhân viên.', 'fields' => [
                'ai_provider_advisor' => ['label' => 'Tư vấn khách (web, Zalo, Facebook, TikTok)', 'type' => 'select', 'options' => Ai::PROVIDERS, 'default' => 'claude'],
                'ai_provider_staff' => ['label' => 'Hỗ trợ nhân viên (gợi ý trả lời, viết SEO)', 'type' => 'select', 'options' => Ai::PROVIDERS, 'default' => 'claude'],
                'ai_provider_internal' => ['label' => 'Giải đáp nội bộ (nhân viên hỏi quy trình trên Workplace)', 'type' => 'select', 'options' => Ai::PROVIDERS, 'default' => 'claude'],
            ]],
            'Khóa API' => ['desc' => 'Mã hóa khi lưu, không hiện lại.', 'fields' => $keys],
            'Trợ lý trên web, tự trả lời' => ['desc' => 'Khách hỏi về hộp thư chung. Bật kênh nào thì AI tự trả lời kênh đó; khiếu nại, đơn lớn, câu khó tự chuyển cho nhân viên.', 'fields' => [
                'chat_web_on' => ['label' => 'Hiện khung chat trên web', 'type' => 'toggle', 'default' => '1', 'hint' => 'Chưa có khóa AI thì khung chat vẫn nhận tin cho nhân viên trả lời.'],
                'ai_auto_web' => ['label' => 'AI trả lời chat web', 'type' => 'toggle', 'default' => '1'],
                'ai_auto_zalo' => ['label' => 'AI trả lời Zalo OA', 'type' => 'toggle'],
                'ai_auto_facebook' => ['label' => 'AI trả lời Messenger', 'type' => 'toggle'],
                'ai_auto_instagram' => ['label' => 'AI trả lời Instagram', 'type' => 'toggle'],
                'ai_auto_tiktok' => ['label' => 'AI trả lời chat TikTok Shop', 'type' => 'toggle'],
            ]],
            'Lời dặn cho trợ lý tư vấn' => ['desc' => 'Giữ phần rào chắn (không nói chữa bệnh, không tự giảm giá, chuyển người thật). Danh mục, giá, tồn kho được hệ thống tự gắn thêm từ dữ liệu sản phẩm.', 'fields' => [
                'ai_advisor_prompt' => ['label' => 'Lời dặn', 'type' => 'textarea', 'default' => Advisor::DEFAULT_PROMPT],
            ]],
        ];
    }

    protected function saved(): void { Cache::forget('ai_catalogue'); }

    public function buttons(): array { return ['testAdvisor' => 'Hỏi thử trợ lý']; }

    public function testAdvisor(): void
    {
        $r = Ai::chat('test', 'advisor', Advisor::system(), [['role' => 'user', 'content' => 'Chào em, anh muốn mua yến biếu bố mẹ, tầm 1 triệu.']], ['effort' => 'low', 'timeout' => 45]);
        $this->status = match ($r['status']) { 'ok' => 'Trợ lý trả lời: ' . mb_strimwidth(Advisor::parse($r['text'])[0], 0, 600, '…'), 'dry_run' => $r['error'] . '. Nhập khóa API rồi lưu.', default => 'Lỗi: ' . $r['error'] };
    }
}
