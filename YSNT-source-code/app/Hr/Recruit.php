<?php
namespace App\Hr;

use App\Models\JobApplication;
use App\Models\OnboardingToken;
use App\Support\Company;
use App\Support\Mailer;

/** Recruitment emails, one per stage, same moments as go365: received, interview invite, rejection, offer. */
class Recruit
{
    private static function mail(?string $to, string $subject, array $lines, string $action, JobApplication $a, array $rows = [], ?array $button = null): void
    {
        if (! $to) return;
        Mailer::send($to, $subject, view('emails.simple', compact('lines', 'rows', 'button'))->render(), $action, $a->code);
    }

    private static function hr(string $subject, array $lines, string $action, JobApplication $a): void
    {
        $rows = ['Mã hồ sơ' => $a->code, 'Vị trí' => $a->position, 'Ứng viên' => $a->name, 'Điện thoại' => $a->phone, 'Email' => (string) $a->email];
        foreach (Hr::notifyEmails() as $to) self::mail($to, '[' . Company::get('brand_name') . ' HR] ' . $subject, $lines, $action . '_hr', $a, $rows,
            ['Mở hồ sơ ứng viên', url('/admin/job-applications/' . $a->id . '/edit')]);
    }

    public static function received(JobApplication $a): void
    {
        self::hr($a->position . ' — ' . $a->name . ' (' . $a->code . ')', ['Có hồ sơ ứng tuyển mới.' . ($a->referrer ? ' Người giới thiệu: ' . $a->referrer->full_name . '.' : '')], 'recruitment_new', $a);
        if ($a->source === 'web') self::mail($a->email, '[' . Company::get('brand_name') . '] Đã nhận hồ sơ ứng tuyển — ' . $a->position, [
            "Chào {$a->name},", 'Cảm ơn bạn đã ứng tuyển vị trí ' . $a->position . '. Hồ sơ của bạn đã được ghi nhận với mã ' . $a->code . '.',
            "Các bước tiếp theo:\n1. Bộ phận nhân sự xem hồ sơ trong 3 đến 5 ngày làm việc.\n2. Nếu phù hợp, chúng tôi gọi điện và gửi thư mời phỏng vấn.\n3. Phỏng vấn tại " . Company::get('showroom_address') . ".\n4. Thông báo kết quả qua email.",
            'Mọi thắc mắc, bạn gọi hotline ' . Company::get('hotline_sales') . ' và đọc mã hồ sơ.'], 'recruitment_ack', $a);
    }

    public static function interview(JobApplication $a): void
    {
        $where = $a->interview_location ?: Company::get('showroom_address');
        $online = str_starts_with($where, 'http');
        $rows = ['Thời gian' => $a->interview_at?->format('H:i, d/m/Y') ?? '', 'Địa điểm' => $online ? 'Phỏng vấn trực tuyến' : $where, 'Người phỏng vấn' => $a->interview_host ?: 'Đại diện tuyển dụng ' . Company::get('brand_name'), 'Mã hồ sơ' => (string) $a->code];
        self::mail($a->email, '[' . Company::get('brand_name') . '] Thư mời phỏng vấn — ' . $a->position, ["Chào {$a->name},", 'Chúng tôi mời bạn phỏng vấn cho vị trí ' . $a->position . '.',
            'Bạn vui lòng đến sớm 5 đến 10 phút. Không đến được, bạn gọi hotline ' . Company::get('hotline_sales') . ' để đổi lịch.'], 'recruitment_interview_invite', $a, $rows,
            $online ? ['Vào phòng phỏng vấn', $where] : ['Xem đường đi', 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($where)]);
        self::hr('Lịch phỏng vấn: ' . $a->name . ' · ' . ($rows['Thời gian']), ['Đã gửi thư mời phỏng vấn tới ứng viên.'], 'recruitment_interview', $a);
    }

    public static function rejected(JobApplication $a, bool $interviewed): void
    {
        self::mail($a->email, '[' . Company::get('brand_name') . '] Thư phản hồi ứng tuyển — ' . $a->position, ["Chào {$a->name},",
            $interviewed ? 'Cảm ơn bạn đã dành thời gian phỏng vấn vị trí ' . $a->position . '. Sau khi cân nhắc, chúng tôi chưa thể tiếp tục cùng bạn lần này. Buổi trò chuyện với bạn rất đáng quý, chúng tôi lưu hồ sơ và sẽ liên hệ khi có vị trí phù hợp hơn.'
                : 'Cảm ơn bạn đã ứng tuyển vị trí ' . $a->position . '. Sau khi xem hồ sơ, chúng tôi chưa thể mời bạn phỏng vấn lần này. Hồ sơ của bạn được lưu lại cho các đợt tuyển sau.',
            'Chúc bạn sớm tìm được công việc phù hợp.'], 'recruitment_reject', $a);
        self::hr('Đã từ chối: ' . $a->name, ['Đã gửi thư phản hồi tới ứng viên.'], 'recruitment_reject', $a);
    }

    /** offer + a 14-day link where the new hire fills in their own profile; HR approves it afterwards */
    public static function hired(JobApplication $a): string
    {
        $link = url('/ho-so-nhan-vien?token=' . OnboardingToken::issue($a));
        self::mail($a->email, '[' . Company::get('brand_name') . '] Chúc mừng bạn trúng tuyển — ' . $a->position, ["Chào {$a->name},",
            'Chúc mừng bạn đã trúng tuyển vị trí ' . $a->position . ' tại ' . Company::get('company_name') . '.',
            'Bạn vui lòng điền hồ sơ nhân viên theo đường dẫn bên dưới trong 14 ngày (thông tin cá nhân, CCCD, bằng cấp). Bộ phận nhân sự sẽ liên hệ về ngày nhận việc.'],
            'recruitment_hired', $a, [], ['Điền hồ sơ nhân viên', $link]);
        self::hr('Trúng tuyển: ' . $a->name, ['Đã gửi thư trúng tuyển kèm đường dẫn điền hồ sơ (hạn 14 ngày).'], 'recruitment_hired', $a);
        return $link;
    }
}
