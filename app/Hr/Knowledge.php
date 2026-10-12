<?php
namespace App\Hr;

use App\Ai\Ai;
use App\Models\Employee;
use App\Models\KbDoc;
use App\Models\KbQuestion;
use App\Support\Company;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** Internal procedures library + AI that answers staff questions only from it, citing the documents it used. */
class Knowledge
{
    public const MAX_TEXT = 300000;

    /** plain text out of an uploaded PDF / Word / text file */
    public static function extract(string $path, string $name): string
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $text = match ($ext) {
            'pdf' => (new \Smalot\PdfParser\Parser())->parseFile($path)->getText(),
            'docx', 'doc', 'odt' => self::word($path, $ext),
            'txt', 'md' => (string) file_get_contents($path),
            default => '',
        };
        $text = preg_replace("/[ \t]+/u", ' ', preg_replace("/\r\n?/", "\n", (string) $text));
        return mb_substr(trim(preg_replace("/\n{3,}/", "\n\n", $text)), 0, self::MAX_TEXT);
    }

    private static function word(string $path, string $ext): string
    {
        $doc = \PhpOffice\PhpWord\IOFactory::load($path, match ($ext) { 'doc' => 'MsDoc', 'odt' => 'ODText', default => 'Word2007' });
        $out = [];
        foreach ($doc->getSections() as $s) foreach ($s->getElements() as $el) $out[] = self::el($el);
        return implode("\n", array_filter($out, fn ($l) => trim($l) !== ''));
    }

    private static function el($el): string
    {
        if ($el instanceof \PhpOffice\PhpWord\Element\Table) {
            return collect($el->getRows())->map(fn ($r) => collect($r->getCells())->map(fn ($c) => trim(collect($c->getElements())->map(fn ($x) => self::el($x))->implode(' ')))->implode(' | '))->implode("\n");
        }
        if ($el instanceof \PhpOffice\PhpWord\Element\ListItem) return '- ' . $el->getTextObject()->getText();
        if (method_exists($el, 'getElements')) return collect($el->getElements())->map(fn ($x) => self::el($x))->implode('');
        if (method_exists($el, 'getText')) { $t = $el->getText(); return is_string($t) ? $t : (is_object($t) ? self::el($t) : ''); }
        return '';
    }

    /** accent-free lower-case words, for ranking */
    public static function words(string $s): array
    {
        $w = preg_split('/[^a-z0-9]+/', strtolower(Str::ascii($s)), -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_filter($w, fn ($x) => strlen($x) > 1));
    }

    /** documents this employee may read, most relevant first, within a character budget */
    public static function pick(?Employee $e, string $question, int $budget): Collection
    {
        $q = array_count_values(self::words($question));
        $docs = KbDoc::with(['department', 'owner'])->visibleTo($e)->get();
        $scored = $docs->map(function (KbDoc $d) use ($q) {
            $title = array_count_values(self::words($d->title . ' ' . $d->category));
            $body = array_count_values(self::words(mb_substr($d->text(), 0, 60000)));
            $score = 0;
            foreach ($q as $w => $n) $score += 5 * ($title[$w] ?? 0) + min(10, $body[$w] ?? 0);
            return [$d, $score];
        })->sortByDesc(fn ($x) => $x[1])->values();
        $out = collect(); $used = 0;
        foreach ($scored as [$d, $score]) {
            $len = mb_strlen($d->text());
            if ($used + $len > $budget && $out->isNotEmpty()) continue;
            $out->push($d); $used += $len;
        }
        return $out;
    }

    public static function ask(?Employee $e, string $question): KbQuestion
    {
        $question = trim(mb_substr($question, 0, 1000));
        $budget = Ai::provider('internal') === 'claude' ? 120000 : 40000;
        $docs = self::pick($e, $question, $budget);
        $row = ['employee_id' => $e?->id, 'question' => $question];
        if ($docs->isEmpty()) return KbQuestion::create($row + ['status' => 'unknown', 'answer' => 'Chưa có tài liệu quy trình nào trong hệ thống. Bạn hỏi trực tiếp quản lý hoặc bộ phận nhân sự giúp mình nhé.']);

        $block = $docs->map(fn (KbDoc $d) => "[TÀI LIỆU {$d->id}] {$d->title}" . ($d->department ? " · bộ phận {$d->department->name}" : ' · toàn công ty')
            . ($d->owner ? " · người phụ trách {$d->owner->full_name}" : '') . ' · cập nhật ' . $d->updated_at?->format('d/m/Y') . "\n" . mb_substr($d->text(), 0, $budget))->implode("\n\n---\n\n");
        $system = 'Bạn là trợ lý giải đáp nội bộ của ' . Company::get('company_name') . ". Nhân viên hỏi về quy trình, quy định, cách làm việc.\n"
            . "Luật bắt buộc:\n- CHỈ trả lời dựa trên các TÀI LIỆU bên dưới. Không dùng hiểu biết bên ngoài, không đoán.\n"
            . "- Sau mỗi ý lấy từ tài liệu, ghi mã nguồn dạng [[DOC:số]] (số là mã tài liệu).\n"
            . "- Tài liệu không nói tới điều được hỏi: trả lời ngắn là tài liệu chưa có nội dung này, gợi ý hỏi người phụ trách tài liệu gần nhất (nếu có), và thêm [[KHONG_CO]].\n"
            . "- Viết tiếng Việt ngắn gọn, đánh số các bước khi là quy trình. Không bịa số liệu, mức tiền, thời hạn.\n\nTÀI LIỆU:\n\n" . $block;
        $res = Ai::chat('kb', 'internal', $system, [['role' => 'user', 'content' => $question]], ['effort' => 'low', 'timeout' => 60]);
        if ($res['status'] !== 'ok') {
            return KbQuestion::create($row + ['status' => $res['status'] === 'dry_run' ? 'dry_run' : 'error', 'doc_ids' => $docs->pluck('id')->take(3)->all(),
                'answer' => $res['status'] === 'dry_run' ? 'AI giải đáp chưa được kết nối. Các tài liệu có thể liên quan được liệt kê bên dưới.' : 'AI đang lỗi, bạn thử lại sau hoặc xem các tài liệu bên dưới.']);
        }
        preg_match_all('/\[\[DOC:\s*(\d+)\s*\]\]/', $res['text'], $m);
        $cited = array_values(array_intersect(array_unique(array_map('intval', $m[1])), $docs->pluck('id')->all()));
        $unknown = str_contains($res['text'], '[[KHONG_CO]]');
        $answer = trim(preg_replace('/\s*\[\[(DOC:\s*\d+|KHONG_CO)\]\]/', '', $res['text']));
        return KbQuestion::create($row + ['status' => $unknown ? 'unknown' : 'answered', 'answer' => $answer, 'doc_ids' => $cited]);
    }
}
