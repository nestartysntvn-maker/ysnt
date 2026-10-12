<?php
namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Support\Carbon;

/**
 * What the business plan ("Kế hoạch kinh doanh 60 tỷ", sections 10-12) commits to build, with the quarter it promised,
 * and where each item stands. Status is kept in app_settings so the board can update it without code.
 */
class Roadmap
{
    public const STATUS = ['chua' => 'Chưa làm', 'dang' => 'Đang làm / chờ thông tin', 'xong' => 'Đang chạy thật'];

    /** code => [group, title, target quarter "Qn/yyyy" or null, default status, what is still missing] */
    public const ITEMS = [
        // section 11: customer experience initiatives
        'gift_phone' => ['Trải nghiệm khách', 'Tặng quà chỉ cần số điện thoại người nhận', 'Q1/2027', 'chua', ''],
        'gift_card' => ['Trải nghiệm khách', 'Thiệp viết tay, thiệp video bằng mã QR', 'Q1/2027', 'chua', ''],
        'tour_order' => ['Trải nghiệm khách', 'Đặt từ xe tour, nhận tại khách sạn (QR gắn mã HDV)', 'Q2/2027', 'chua', ''],
        'web_zh_ko' => ['Trải nghiệm khách', 'Web tiếng Trung, tiếng Hàn', 'Q2/2027', 'chua', 'Cần người dịch nội dung'],
        'gift_calendar' => ['Trải nghiệm khách', 'Lịch quà: nhắc dịp quan trọng qua Zalo', 'Q2/2027', 'chua', 'Cần Zalo OA, ZNS'],
        'club' => ['Trải nghiệm khách', 'Nest Art Club: hạng thành viên, quà sinh nhật', 'Q2/2027', 'chua', ''],
        'passport' => ['Trải nghiệm khách', 'Hộ chiếu tổ yến: QR truy xuất từng hộp', 'Q3/2027', 'chua', 'Nhà máy in QR theo lô'],
        'subscription' => ['Trải nghiệm khách', 'Gói yến định kỳ cho bố mẹ', 'Q3/2027', 'chua', ''],
        'corp_portal' => ['B2B', 'Cổng quà tặng doanh nghiệp: tải danh sách, in logo, báo giá theo bậc', 'Q4/2027', 'chua', ''],
        'agent_portal' => ['B2B', 'Trang đại lý: tự đặt hàng, xem công nợ, tải ảnh bán hàng', 'Q4/2027', 'chua', ''],
        // section 10: ERP stages
        'erp_promo' => ['ERP bán hàng', 'Khuyến mãi, bảng giá theo kênh', 'Q1/2027', 'chua', ''],
        'erp_returns' => ['ERP bán hàng', 'Đổi trả, khiếu nại, phê duyệt', 'Q1/2027', 'chua', ''],
        'erp_affiliate' => ['ERP bán hàng', 'Cấp bậc affiliate, chống gian lận mã giới thiệu', 'Q1/2027', 'chua', ''],
        'erp_stock' => ['ERP hàng hóa', 'Đặt hàng nhà máy, nhận theo lô và hạn dùng, kho nhiều điểm, kiểm kê', 'Q2/2027', 'chua', 'Cần danh sách kho, hình thức nhập từ nhà máy'],
        'erp_label' => ['ERP hàng hóa', 'In tem mã vạch, QR; thu hồi theo lô', 'Q2/2027', 'chua', ''],
        'erp_ship' => ['ERP giao vận, tiền', 'Vận đơn, đối soát COD và tiền sàn, công nợ', 'Q2/2027', 'chua', 'Chọn đơn vị vận chuyển'],
        'erp_pnl' => ['ERP giao vận, tiền', 'Lãi lỗ theo kênh, hóa đơn điện tử, đồng bộ kế toán', 'Q3/2027', 'chua', 'Phần mềm kế toán, hóa đơn điện tử đang dùng'],
        'erp_kpi' => ['ERP nội bộ', 'Giao việc, nhắc việc, KPI và thưởng bán hàng', 'Q2/2027', 'dang', 'Giao việc, nhắc việc đã có; còn KPI, thưởng'],
        'erp_monday' => ['ERP nội bộ', 'Báo cáo thứ Hai tự gửi ban giám đốc', 'Q1/2027', 'chua', ''],
        'erp_payroll' => ['ERP nội bộ', 'Chấm công, lương', null, 'chua', ''],
        // built in code, waiting for accounts / data (section 12, 13, connections)
        'inbox' => ['Đã có code, chờ kết nối', 'Hộp thư chung Zalo, Messenger, Instagram, TikTok', 'Q3/2027', 'dang', 'Chờ Zalo OA, Fanpage, app TikTok'],
        'ai_advisor' => ['Đã có code, chờ kết nối', 'AI tư vấn trên web và Zalo', 'Q1/2027', 'dang', 'Chờ khóa API AI'],
        'zns8' => ['Đã có code, chờ kết nối', 'Zalo tự động đủ 8 mẫu tin', 'Q1/2027', 'dang', 'Chờ Zalo duyệt mẫu ZNS'],
        'marketplaces' => ['Đã có code, chờ kết nối', 'Kéo đơn, đẩy tồn TikTok Shop, Shopee, Lazada', 'Q1/2027', 'dang', 'Chờ sàn duyệt ứng dụng'],
        'pixel' => ['Đã có code, chờ kết nối', 'Đo đơn từ quảng cáo (Meta Pixel, GA4)', 'Q1/2027', 'dang', 'Chờ Pixel ID, GA4'],
        'workplace' => ['Đã có code, chờ kết nối', 'Workplace nhân viên, AI giải đáp quy trình', 'Q1/2027', 'dang', 'Cần danh sách nhân viên, quy trình'],
        'own_server' => ['Hạ tầng', 'Chuyển hệ thống sang máy chủ, tên miền của YSNT', 'Q1/2027', 'chua', 'Cần tài khoản VPS, tên miền'],
    ];

    private static function stored(): array { return json_decode((string) AppSetting::get('roadmap'), true) ?: []; }

    /** @return array<int,array{code:string,group:string,title:string,target:?string,status:string,note:string,late:bool}> */
    public static function all(): array
    {
        $s = self::stored(); $out = [];
        foreach (self::ITEMS as $code => [$group, $title, $target, $def, $missing]) {
            $st = $s[$code]['s'] ?? $def;
            $out[] = ['code' => $code, 'group' => $group, 'title' => $title, 'target' => $target, 'status' => isset(self::STATUS[$st]) ? $st : $def,
                'note' => (string) ($s[$code]['n'] ?? $missing), 'late' => $st !== 'xong' && self::passed($target)];
        }
        return $out;
    }

    /** true when the promised quarter has already ended */
    public static function passed(?string $q): bool
    {
        if (! $q || ! preg_match('/^Q([1-4])\/(\d{4})$/', $q, $m)) return false;
        return now('Asia/Ho_Chi_Minh')->gt(Carbon::create((int) $m[2], (int) $m[1] * 3, 1, 0, 0, 0, 'Asia/Ho_Chi_Minh')->endOfMonth());
    }

    public static function save(array $statuses, array $notes): void
    {
        $out = [];
        foreach (self::ITEMS as $code => $_) $out[$code] = ['s' => isset(self::STATUS[$statuses[$code] ?? '']) ? $statuses[$code] : self::ITEMS[$code][3], 'n' => mb_substr(trim((string) ($notes[$code] ?? '')), 0, 200)];
        AppSetting::put('roadmap', json_encode($out, JSON_UNESCAPED_UNICODE));
    }
}
