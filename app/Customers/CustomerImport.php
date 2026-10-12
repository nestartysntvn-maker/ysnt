<?php
namespace App\Customers;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\CustomerImportBatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as XlsDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Old customer lists (Excel, CSV, or cells pasted from Excel) -> checked rows -> one batch that can be undone.
 * readFile / prepare / check only read; nothing is written until commit().
 */
class CustomerImport
{
    public const FIELDS = ['name' => 'Họ tên', 'phone' => 'Số điện thoại', 'email' => 'Email', 'address' => 'Địa chỉ', 'birthday' => 'Ngày sinh', 'group' => 'Nhóm khách', 'source' => 'Nguồn', 'note' => 'Ghi chú'];
    public const MAX_ROWS = 20000;

    /** column titles people use (accents removed): exact title first, then "title contains" */
    private const SYN = [
        'name' => ['ho ten', 'ho va ten', 'ten', 'ten khach', 'ten khach hang', 'khach hang', 'ten kh', 'kh', 'name', 'full name', 'fullname', 'customer', 'customer name', 'nguoi mua'],
        'phone' => ['so dien thoai', 'sdt', 'dien thoai', 'dt', 'so dt', 'phone', 'mobile', 'tel', 'phone number', 'zalo', 'sdt zalo', 'di dong', 'so di dong', 'lien he'],
        'email' => ['email', 'e mail', 'mail', 'thu dien tu'],
        'address' => ['dia chi', 'address', 'dia chi giao hang', 'tinh', 'tinh thanh', 'tinh tp', 'khu vuc'],
        'birthday' => ['ngay sinh', 'sinh nhat', 'dob', 'birthday', 'nam sinh', 'ngay thang nam sinh'],
        'group' => ['nhom', 'nhom khach', 'nhom khach hang', 'loai khach', 'loai khach hang', 'phan loai', 'group', 'loai'],
        'source' => ['nguon', 'nguon khach', 'kenh', 'kenh ban', 'source', 'biet qua', 'den tu'],
        'note' => ['ghi chu', 'note', 'notes', 'mo ta', 'luu y'],
    ];
    private const CON = [
        'name' => ['ho ten', 'ho va ten', 'ten khach', 'ten kh', 'full name', 'customer name'],
        'phone' => ['so dien thoai', 'dien thoai', 'sdt', 'so dt', 'phone', 'mobile', 'di dong', 'zalo'],
        'email' => ['email', 'e mail'],
        'address' => ['dia chi', 'address'],
        'birthday' => ['ngay sinh', 'sinh nhat', 'birthday', 'nam sinh'],
        'group' => ['nhom', 'loai khach', 'phan loai'],
        'source' => ['nguon', 'kenh'],
        'note' => ['ghi chu', 'note', 'notes'],
    ];
    /** 11-digit mobile prefixes renamed to 10 digits in 2018 */
    public const OLD_PREFIX = ['0162' => '032', '0163' => '033', '0164' => '034', '0165' => '035', '0166' => '036', '0167' => '037', '0168' => '038', '0169' => '039',
        '0120' => '070', '0121' => '079', '0122' => '077', '0126' => '076', '0128' => '078', '0123' => '083', '0124' => '084', '0125' => '085', '0127' => '081', '0129' => '082',
        '0186' => '056', '0188' => '058', '0199' => '059'];
    private const GROUP_WORDS = ['doanh_nghiep' => ['doanh nghiep', 'cong ty', 'dn', 'b2b', 'to chuc'], 'dai_ly' => ['dai ly', 'dl', 'si', 'nha phan phoi', 'npp'],
        'quoc_te' => ['quoc te', 'nuoc ngoai', 'foreign', 'foreigner', 'khach tay'], 'le' => ['le', 'khach le', 'ca nhan', 'b2c', 'retail']];
    private const SOURCE_WORDS = ['tiktok' => ['tiktok', 'tik tok'], 'shopee' => ['shopee'], 'web' => ['web', 'website', 'online'], 'showroom' => ['showroom', 'cua hang', 'tai cho', 'tai quay'],
        'hdv' => ['hdv', 'huong dan vien', 'tai xe', 'xe tour'], 'dai_ly' => ['dai ly'], 'hoi_cho' => ['hoi cho', 'su kien', 'event'], 'danh_sach_cu' => ['danh sach cu']];

    public static function fold(mixed $s): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', strtolower(Str::ascii((string) $s))));
    }

    private static function hasWord(string $folded, array $words): bool
    {
        foreach ($words as $w) if ($folded === $w || str_contains(" $folded ", " $w ")) return true;
        return false;
    }

    /**
     * Vietnamese mobile number: spaces/dots removed, +84 -> 0, the 0 that Excel drops is put back,
     * old 11-digit prefixes renamed. Landlines (02...) are refused: orders and Zalo use mobiles.
     * @return array{v:string, fix:list<string>, extra:list<string>, err:?string}
     */
    public static function phone(mixed $raw): array
    {
        $orig = trim((string) $raw);
        $bad = fn (string $why) => ['v' => $orig, 'fix' => [], 'extra' => [], 'err' => $why];
        if ($orig === '') return $bad('Thiếu số điện thoại');
        $parts = array_values(array_filter(preg_split('/\s*[\/,;|]\s*|\s+-\s+|\s+(?:hoặc|hoac|or)\s+/iu', $orig) ?: [], fn ($p) => $p !== ''));
        $s = $parts[0] ?? '';
        $fix = [];
        if (preg_match('/^\d+(\.\d+)?e\+?\d+$/i', $s)) $s = sprintf('%.0f', (float) $s);
        if (preg_match('/\+?\d[\d\s.\-()]{6,}\d/', $s, $m)) $s = $m[0];
        $s = (string) preg_replace('/[^\d+]/', '', $s);
        $s = (string) preg_replace('/(?!^)\+/', '', $s);
        if (preg_replace('/\D/', '', $s) === '') return $bad('Thiếu số điện thoại');
        if (preg_match('/^\+?84\d{9,11}$/', $s)) {
            $s = '0' . preg_replace('/^0/', '', (string) preg_replace('/^\+?84/', '', $s));
            $fix[] = 'đổi +84 thành 0';
        } elseif (preg_match('/^(?:[35789]\d{8}|[12]\d{9})$/', $s)) {
            $s = '0' . $s;
            $fix[] = 'thêm số 0 Excel làm mất';
        }
        if (preg_match('/^01\d{9}$/', $s) && isset(self::OLD_PREFIX[substr($s, 0, 4)])) {
            $o = substr($s, 0, 4);
            $s = self::OLD_PREFIX[$o] . substr($s, 4);
            $fix[] = "đổi đầu số cũ $o thành " . self::OLD_PREFIX[$o];
        }
        if (preg_match('/^02\d{9}$/', $s)) return $bad('Số máy bàn, cần số di động');
        if (! preg_match('/^0[35789]\d{8}$/', $s)) return $bad('Số điện thoại sai, cần 10 số di động');
        $extra = array_values(array_filter(array_slice($parts, 1), fn ($p) => strlen((string) preg_replace('/\D/', '', $p)) >= 8));
        if ($extra) $fix[] = 'số thứ hai đưa vào ghi chú';
        return ['v' => $s, 'fix' => $fix, 'extra' => $extra, 'err' => null];
    }

    /** "15/03/1985", "15-3-85", "1985-03-15" or an Excel date number -> Y-m-d; a bare year or anything unclear -> null */
    public static function birthday(mixed $raw): ?string
    {
        $s = trim((string) $raw);
        if ($s === '') return null;
        if (preg_match('/^\d{5}(\.\d+)?$/', $s) && (float) $s >= 10000 && (float) $s < 60000) {
            $dt = XlsDate::excelToDateTimeObject((float) $s);
            return (int) $dt->format('Y') <= (int) date('Y') ? $dt->format('Y-m-d') : null;
        }
        if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2}|\d{4})$/', $s, $m)) [$d, $mo, $y] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        elseif (preg_match('/^(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})/', $s, $m)) [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        else return null;
        if ($y < 100) $y += $y > (int) date('y') ? 1900 : 2000;
        if (! checkdate($mo, $d, $y) && checkdate($d, $mo, $y)) [$d, $mo] = [$mo, $d]; // 3/15/1985 written the US way
        if ($y < 1900 || $y > (int) date('Y') || ! checkdate($mo, $d, $y)) return null;
        return sprintf('%04d-%02d-%02d', $y, $mo, $d);
    }

    public static function group(mixed $raw): ?string
    {
        $f = self::fold($raw);
        if ($f === '') return null;
        foreach (self::GROUP_WORDS as $code => $words) if (self::hasWord($f, $words)) return $code;
        return null;
    }

    public static function source(mixed $raw): ?string
    {
        $f = self::fold($raw);
        if ($f === '') return null;
        foreach (self::SOURCE_WORDS as $code => $words) if (self::hasWord($f, $words)) return $code;
        return null;
    }

    /** @return array<string,int> field => column index (-1 = not used) */
    public static function autoMap(array $headers): array
    {
        $H = array_map(fn ($h) => self::fold($h), array_values($headers));
        $used = [];
        $m = [];
        foreach (array_keys(self::FIELDS) as $f) {
            $m[$f] = -1;
            foreach ($H as $k => $h) if (! isset($used[$k]) && in_array($h, self::SYN[$f], true)) { $m[$f] = $k; $used[$k] = true; break; }
        }
        foreach (array_keys(self::FIELDS) as $f) {
            if ($m[$f] >= 0) continue;
            foreach ($H as $k => $h) if ($h !== '' && ! isset($used[$k]) && self::hasWord($h, self::CON[$f])) { $m[$f] = $k; $used[$k] = true; break; }
        }
        return $m;
    }

    /** first of the top rows that names both a name column and a phone column */
    public static function headerIndex(array $rows): ?int
    {
        foreach (array_slice($rows, 0, 12, true) as $i => $r) {
            $m = self::autoMap((array) $r);
            if ($m['name'] >= 0 && $m['phone'] >= 0) return $i;
        }
        return null;
    }

    /** @return array{0: list<list<string>>, 1: string} rows of the sheet that holds the list, its name ('' if only one sheet) */
    public static function readFile(string $path, string $name): array
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (in_array($ext, ['csv', 'txt'], true)) return [self::parseDelimited(self::decode((string) file_get_contents($path))), ''];
        if (! in_array($ext, ['xlsx', 'xls'], true)) throw new ImportError('Chỉ nhận file .xlsx, .xls hoặc .csv.');
        @ini_set('memory_limit', '512M');
        @set_time_limit(120);
        try {
            try {
                $reader = IOFactory::createReader($ext === 'xls' ? 'Xls' : 'Xlsx');
                $reader->setReadDataOnly(true);
                $book = $reader->load($path);
            } catch (\Throwable) {
                $reader = IOFactory::createReaderForFile($path); // .xls that is really an HTML table, etc.
                $reader->setReadDataOnly(true);
                $book = $reader->load($path);
            }
        } catch (\Throwable $e) {
            $msg = strtolower($e->getMessage());
            throw new ImportError(str_contains($msg, 'password') || str_contains($msg, 'encrypt')
                ? 'File đang đặt mật khẩu. Mở file, bỏ mật khẩu rồi chọn lại.'
                : 'Không đọc được file này. Mở bằng Excel, lưu lại dạng .xlsx rồi chọn lại.');
        }
        $sheets = [];
        foreach ($book->getWorksheetIterator() as $ws) {
            $rows = [];
            foreach ($ws->toArray(null, true, false, false) as $r) $rows[] = array_map(fn ($v) => self::cell($v), (array) $r);
            $sheets[] = [$ws->getTitle(), $rows];
        }
        $book->disconnectWorksheets();
        $pick = null;
        foreach ($sheets as $s) if (self::headerIndex(array_values(array_filter($s[1], fn ($r) => implode('', $r) !== ''))) !== null) { $pick = $s; break; }
        if (! $pick) foreach ($sheets as $s) if (array_filter($s[1], fn ($r) => implode('', $r) !== '')) { $pick = $s; break; }
        $pick ??= $sheets[0] ?? ['', []];
        return [$pick[1], count($sheets) > 1 ? (string) $pick[0] : ''];
    }

    private static function cell(mixed $v): string
    {
        if ($v === null) return '';
        if (is_bool($v)) return $v ? 'TRUE' : 'FALSE';
        if (is_float($v) && floor($v) == $v && abs($v) < 1e15) return sprintf('%.0f', $v); // phone typed as a number
        $s = trim((string) $v);
        return (string) (\Normalizer::normalize($s, \Normalizer::FORM_C) ?: $s);
    }

    /** CSV bytes -> UTF-8 text: UTF-8 (with or without BOM), UTF-16 ("Unicode Text" from Excel) or old Windows Vietnamese (CP1258) */
    public static function decode(string $b): string
    {
        if (str_starts_with($b, "\xFF\xFE")) $t = mb_convert_encoding(substr($b, 2), 'UTF-8', 'UTF-16LE');
        elseif (str_starts_with($b, "\xFE\xFF")) $t = mb_convert_encoding(substr($b, 2), 'UTF-8', 'UTF-16BE');
        else {
            if (str_starts_with($b, "\xEF\xBB\xBF")) $b = substr($b, 3);
            $t = mb_check_encoding($b, 'UTF-8') ? $b : (@iconv('CP1258', 'UTF-8//IGNORE', $b) ?: mb_convert_encoding($b, 'UTF-8', 'Windows-1252'));
        }
        return (string) (\Normalizer::normalize($t, \Normalizer::FORM_C) ?: $t);
    }

    /** text with tab, ; or , between cells (picked from the first line), quotes and line breaks inside quotes allowed */
    public static function parseDelimited(string $text, ?string $d = null): array
    {
        if (str_starts_with($text, "\xEF\xBB\xBF")) $text = substr($text, 3);
        if ($d === null) {
            $first = (string) strtok($text, "\n");
            $d = "\t";
            $best = substr_count($first, "\t");
            foreach ([';', ','] as $c) if (substr_count($first, $c) > $best) { $d = $c; $best = substr_count($first, $c); }
        }
        $h = fopen('php://temp', 'r+');
        fwrite($h, $text);
        rewind($h);
        $rows = [];
        while (($r = fgetcsv($h, 0, $d, '"', '')) !== false) $rows[] = array_map(fn ($c) => trim((string) $c), $r);
        fclose($h);
        return $rows;
    }

    /**
     * Raw rows -> header, data rows (with their line numbers in the file), suggested column for each field.
     * A big title above the header is skipped; a file without any header gets columns guessed from the data.
     * @return array{headers: list<string>, rows: list<list<string>>, lines: list<int>, map: array<string,int>, headRow: int}
     */
    public static function prepare(array $raw): array
    {
        $all = [];
        foreach (array_values($raw) as $i => $r) {
            $r = array_map(fn ($c) => trim((string) $c), (array) $r);
            if (implode('', $r) !== '') $all[] = [$r, $i + 1];
        }
        if (! $all) throw new ImportError('Không thấy dữ liệu trong file.');
        $h = self::headerIndex(array_column($all, 0));
        $noHead = false;
        if ($h === null) {
            $noHead = (bool) array_filter($all[0][0], fn ($c) => self::phone($c)['err'] === null);
            $h = $noHead ? -1 : 0;
        }
        $data = array_slice($all, $h + 1);
        if (! $data) throw new ImportError('Chỉ thấy dòng tiêu đề, chưa có dòng khách nào bên dưới.');
        if (count($data) > self::MAX_ROWS) throw new ImportError('File có ' . number_format(count($data), 0, ',', '.') . ' dòng khách, quá ' . number_format(self::MAX_ROWS, 0, ',', '.') . ' dòng. Chia nhỏ file rồi nhập từng phần.');
        $w = max(array_map(fn ($x) => count($x[0]), $all));
        $headers = [];
        for ($i = 0; $i < $w; $i++) {
            $t = $h >= 0 ? (string) ($all[$h][0][$i] ?? '') : '';
            $headers[] = $t !== '' ? $t : 'Cột ' . self::colName($i);
        }
        $rows = array_map(fn ($x) => array_pad($x[0], $w, ''), $data);
        $map = $noHead ? array_fill_keys(array_keys(self::FIELDS), -1) : self::autoMap($headers);
        self::guess($map, $rows, $w);
        return ['headers' => $headers, 'rows' => $rows, 'lines' => array_column($data, 1), 'map' => $map, 'headRow' => $h >= 0 ? $all[$h][1] : 0];
    }

    public static function colName(int $i): string
    {
        return ($i >= 26 ? self::colName(intdiv($i, 26) - 1) : '') . chr(65 + $i % 26);
    }

    /** columns the header did not name: the one that is mostly phone numbers, mostly emails, mostly people's names */
    private static function guess(array &$m, array $rows, int $w): void
    {
        $used = [];
        foreach ($m as $i) if ($i >= 0) $used[$i] = true;
        $sample = array_slice($rows, 0, 200);
        $share = function (int $i, callable $ok) use ($sample): float {
            $v = array_values(array_filter(array_map(fn ($r) => (string) ($r[$i] ?? ''), $sample), fn ($x) => $x !== ''));
            return $v ? count(array_filter($v, $ok)) / count($v) : 0.0;
        };
        foreach (['phone' => fn ($c) => self::phone($c)['err'] === null, 'email' => fn ($c) => (bool) filter_var($c, FILTER_VALIDATE_EMAIL)] as $f => $ok) {
            if ($m[$f] >= 0) continue;
            $best = -1;
            $bs = 0.6;
            for ($i = 0; $i < $w; $i++) if (! isset($used[$i]) && ($s = $share($i, $ok)) > $bs) { $bs = $s; $best = $i; }
            $m[$f] = $best;
            if ($best >= 0) $used[$best] = true;
        }
        if ($m['name'] < 0) {
            for ($i = 0; $i < $w; $i++) {
                if (isset($used[$i])) continue;
                if ($share($i, fn ($c) => preg_match('/\p{L}{2}/u', $c) && ! preg_match('/[\d@]/', $c) && mb_strlen($c) <= 60) > 0.6) { $m['name'] = $i; break; }
            }
        }
    }

    /**
     * Check every row against the rules and against customers already saved. Reads only.
     * st: new | dupf (same number earlier in the file) | dupc (already a customer) | err
     * @return list<array<string,mixed>>
     */
    public static function check(array $rows, array $lines, array $map): array
    {
        $get = fn (array $r, string $f) => ($map[$f] ?? -1) >= 0 ? trim((string) ($r[$map[$f]] ?? '')) : '';
        $out = [];
        foreach (array_values($rows) as $i => $r) {
            $ph = self::phone($get($r, 'phone'));
            $o = ['i' => $i, 'line' => $lines[$i] ?? $i + 2, 'name' => (string) preg_replace('/\s+/u', ' ', $get($r, 'name')), 'phone' => $ph['v'], 'fix' => $ph['fix'],
                'email' => mb_strtolower($get($r, 'email')), 'address' => $get($r, 'address'), 'note' => $get($r, 'note'), 'st' => 'ok', 'why' => '', 'old' => null, 'fill' => []];
            $notes = [];
            if ($ph['extra']) $notes[] = 'SĐT khác: ' . implode(', ', $ph['extra']);
            if ($o['email'] !== '' && ! filter_var($o['email'], FILTER_VALIDATE_EMAIL)) { $o['email'] = ''; $o['fix'][] = 'email sai nên để trống'; }
            $dob = $get($r, 'birthday');
            $o['birthday'] = self::birthday($dob);
            if ($dob !== '' && ! $o['birthday']) { $notes[] = 'Ngày sinh: ' . $dob; $o['fix'][] = 'ngày sinh chưa rõ, ghi vào ghi chú'; }
            $g = $get($r, 'group');
            $o['group'] = self::group($g);
            if ($g !== '' && ! $o['group']) $notes[] = 'Nhóm trong file: ' . $g;
            $src = $get($r, 'source');
            $o['source'] = self::source($src);
            if ($src !== '' && ! $o['source']) $notes[] = 'Nguồn trong file: ' . $src;
            if ($notes) $o['note'] = implode(' · ', array_filter([$o['note'], ...$notes]));
            if (mb_strlen($o['name']) < 2) [$o['st'], $o['why']] = ['err', 'Thiếu họ tên'];
            elseif ($ph['err']) [$o['st'], $o['why']] = ['err', $ph['err']];
            $out[] = $o;
        }
        $seen = [];
        foreach ($out as &$o) {
            if ($o['st'] !== 'ok') continue;
            if (isset($seen[$o['phone']])) [$o['st'], $o['why']] = ['dupf', 'Trùng số với dòng ' . $seen[$o['phone']] . ' trong file'];
            else $seen[$o['phone']] = $o['line'];
        }
        unset($o);
        $have = [];
        foreach (array_chunk(array_keys($seen), 1000) as $chunk)
            foreach (Customer::whereIn('phone', array_map('strval', $chunk))->get(['id', 'name', 'phone', 'email', 'address', 'birthday', 'note']) as $c) $have[$c->phone] = $c;
        foreach ($out as &$o) {
            if ($o['st'] !== 'ok') continue;
            $c = $have[$o['phone']] ?? null;
            if (! $c) { $o['st'] = 'new'; continue; }
            [$o['st'], $o['why'], $o['old']] = ['dupc', 'Đã có: ' . $c->name, ['id' => $c->id, 'name' => $c->name]];
            foreach (['email', 'address', 'note'] as $k) if ($o[$k] !== '' && blank($c->getRawOriginal($k))) $o['fill'][$k] = $o[$k];
            if ($o['birthday'] && blank($c->getRawOriginal('birthday'))) $o['fill']['birthday'] = $o['birthday'];
        }
        unset($o);
        return $out;
    }

    /**
     * Save one batch: new customers, and (when asked) blank fields of existing customers filled in.
     * Rows are re-checked by the caller just before this runs. One summary line goes to the activity log.
     * @param array{dup: string, source: string, group: string} $opt source '__file' = take it from the file, else "Danh sách cũ"
     */
    public static function commit(array $checked, string $fileName, User $user, array $opt): CustomerImportBatch
    {
        return DB::transaction(function () use ($checked, $fileName, $user, $opt) {
            $b = CustomerImportBatch::create(['user_id' => $user->id, 'file_name' => mb_strimwidth($fileName, 0, 250, '…'), 'total_rows' => count($checked)]);
            $now = now();
            $new = [];
            $filled = [];
            $skipped = 0;
            $errors = 0;
            foreach ($checked as $o) {
                if ($o['st'] === 'err') { $errors++; continue; }
                if ($o['st'] === 'dupf') { $skipped++; continue; }
                if ($o['st'] === 'dupc') {
                    $c = $opt['dup'] === 'fill' && $o['fill'] ? Customer::lockForUpdate()->find($o['old']['id']) : null;
                    $fields = [];
                    if ($c) foreach ($o['fill'] as $k => $v) if (blank($c->getRawOriginal($k))) $fields[$k] = ['before' => $c->getRawOriginal($k), 'after' => $v];
                    if ($fields) {
                        Customer::withoutEvents(fn () => $c->forceFill(array_map(fn ($x) => $x['after'], $fields))->save());
                        $filled[] = ['id' => $c->id, 'fields' => $fields];
                    } else $skipped++;
                    continue;
                }
                $new[] = ['name' => mb_substr($o['name'], 0, 255), 'phone' => $o['phone'], 'email' => $o['email'] !== '' ? mb_substr($o['email'], 0, 255) : null,
                    'address' => $o['address'] !== '' ? $o['address'] : null, 'birthday' => $o['birthday'], 'customer_group' => $o['group'] ?: $opt['group'],
                    'source' => $opt['source'] === '__file' ? ($o['source'] ?: 'danh_sach_cu') : $opt['source'], 'note' => $o['note'] !== '' ? $o['note'] : null,
                    'import_batch_id' => $b->id, 'created_at' => $now, 'updated_at' => $now];
            }
            foreach (array_chunk($new, 500) as $chunk) DB::table('customers')->insert($chunk);
            $b->update(['added_count' => count($new), 'filled_count' => count($filled), 'skipped_count' => $skipped, 'error_count' => $errors, 'filled' => $filled ?: null]);
            ActivityLog::create(['user_id' => $user->id, 'action' => 'import', 'subject_type' => 'CustomerImportBatch', 'subject_id' => $b->id,
                'changes' => ['file' => $b->file_name, 'added' => count($new), 'filled' => count($filled), 'skipped' => $skipped, 'errors' => $errors], 'ip' => request()?->ip()]);
            return $b;
        });
    }

    /**
     * Undo a batch: remove its customers that have not ordered since, put back the blank fields it filled
     * (only where nobody changed them afterwards). Customers who already ordered are kept.
     * @return array{removed:int, kept:int, restored:int}
     */
    public static function undo(CustomerImportBatch $batch, User $user): array
    {
        return DB::transaction(function () use ($batch, $user) {
            $b = CustomerImportBatch::lockForUpdate()->findOrFail($batch->id);
            if ($b->undone_at) throw new ImportError('Lô này đã hoàn tác rồi.');
            $total = Customer::where('import_batch_id', $b->id)->count();
            $removed = Customer::where('import_batch_id', $b->id)->whereDoesntHave('orders')->delete();
            $restored = 0;
            foreach ($b->filled ?? [] as $f) {
                $c = Customer::find($f['id']);
                if (! $c) continue;
                $set = [];
                foreach ($f['fields'] as $k => $x) if ((string) $c->getRawOriginal($k) === (string) $x['after']) $set[$k] = $x['before'];
                if ($set) { Customer::withoutEvents(fn () => $c->forceFill($set)->save()); $restored++; }
            }
            $b->update(['undone_at' => now(), 'undone_by' => $user->id]);
            $r = ['removed' => $removed, 'kept' => $total - $removed, 'restored' => $restored];
            ActivityLog::create(['user_id' => $user->id, 'action' => 'import_undo', 'subject_type' => 'CustomerImportBatch', 'subject_id' => $b->id, 'changes' => $r, 'ip' => request()?->ip()]);
            return $r;
        });
    }

    /** rows -> .xlsx download; every cell written as text so 0905... keeps its leading 0 */
    public static function download(string $name, array $rows): StreamedResponse
    {
        $book = new Spreadsheet();
        $ws = $book->getActiveSheet();
        $ws->setTitle('Khách hàng');
        $w = max(1, count($rows[0] ?? []));
        foreach (array_values($rows) as $r => $row)
            foreach (array_values($row) as $c => $v) $ws->setCellValueExplicit([$c + 1, $r + 1], (string) $v, DataType::TYPE_STRING);
        for ($c = 1; $c <= $w; $c++) $ws->getColumnDimensionByColumn($c)->setWidth(20);
        $ws->getStyle([1, 1, $w, 1])->getFont()->setBold(true);
        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
            $book->disconnectWorksheets();
        }, $name, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
