<?php
namespace App\Filament\Resources\Customers\Pages;

use App\Customers\CustomerImport;
use App\Customers\ImportError;
use App\Filament\Resources\Customers\CustomerResource;
use App\Models\Customer;
use App\Models\CustomerImportBatch;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\WithFileUploads;

/**
 * Khách hàng > Nhập danh sách: choose a file (or paste) -> match columns -> check (nothing saved yet) -> save one batch that can be undone.
 * The parsed list waits in storage/app/private/customer-imports/<token>.json (deleted after a day); only small state lives in Livewire.
 */
class ImportCustomers extends Page
{
    use WithFileUploads;

    protected static string $resource = CustomerResource::class;
    protected string $view = 'filament.resources.customers.import';
    protected static ?string $title = 'Nhập danh sách khách hàng';
    protected static ?string $breadcrumb = 'Nhập danh sách';

    #[Locked] public int $step = 1;
    #[Locked] public string $token = '';
    #[Locked] public string $fileName = '';
    #[Locked] public string $sheet = '';
    #[Locked] public int $headRow = 0;
    #[Locked] public array $headers = [];
    #[Locked] public int $total = 0;
    #[Locked] public ?array $result = null;
    public $upload = null;
    public string $pasted = '';
    public string $err = '';
    public array $map = [];
    public string $filter = 'all';
    public string $dup = 'skip';
    public string $src = '__file';
    public string $grp = 'le';

    private ?array $memo = null;

    /** Quản trị and Bán hàng (hasRole() is always true for admin) */
    public static function canAccess(array $parameters = []): bool
    {
        return (bool) auth()->user()?->hasRole('sales');
    }

    public function updatedUpload(): void
    {
        $this->err = '';
        $this->resetErrorBag('upload');
        $f = $this->upload;
        $this->upload = null;
        if (! $f) return;
        try {
            if ($f->getSize() > 2 * 1024 * 1024) throw new ImportError('File lớn hơn 2MB. Chia nhỏ file rồi nhập từng phần.');
            [$rows, $sheet] = CustomerImport::readFile($f->getRealPath(), $f->getClientOriginalName());
            $this->load($rows, $f->getClientOriginalName(), $sheet);
        } catch (ImportError $e) {
            $this->err = $e->getMessage();
        } finally {
            try { $f->delete(); } catch (\Throwable) {}
        }
    }

    public function usePaste(): void
    {
        $this->err = '';
        if (trim($this->pasted) === '') { $this->err = 'Chưa có dữ liệu trong ô dán.'; return; }
        try {
            $this->load(CustomerImport::parseDelimited($this->pasted, str_contains($this->pasted, "\t") ? "\t" : null), 'Dữ liệu dán', '');
        } catch (ImportError $e) {
            $this->err = $e->getMessage();
        }
    }

    private function load(array $raw, string $name, string $sheet): void
    {
        $p = CustomerImport::prepare($raw);
        $disk = Storage::disk('local');
        foreach ($disk->files('customer-imports') as $old) if ($disk->lastModified($old) < now()->subDay()->getTimestamp()) $disk->delete($old);
        $this->token = (string) Str::uuid();
        $disk->put($this->path(), json_encode(['headers' => $p['headers'], 'rows' => $p['rows'], 'lines' => $p['lines']], JSON_UNESCAPED_UNICODE));
        $this->fileName = mb_strimwidth($name, 0, 120, '…');
        $this->sheet = $sheet;
        $this->headRow = $p['headRow'];
        $this->headers = $p['headers'];
        $this->map = $p['map'];
        $this->total = count($p['rows']);
        $this->filter = 'all';
        $this->result = null;
        $this->memo = null;
        $this->step = 2;
    }

    private function path(): string
    {
        abort_unless(preg_match('/^[0-9a-f-]{36}$/', $this->token) === 1, 404);
        return 'customer-imports/' . $this->token . '.json';
    }

    private function data(): array
    {
        $d = json_decode((string) Storage::disk('local')->get($this->path()), true);
        return is_array($d) ? $d : ['headers' => [], 'rows' => [], 'lines' => []];
    }

    /** column choices sent back by the browser, kept to real columns */
    public function cleanMap(): array
    {
        $m = [];
        foreach (array_keys(CustomerImport::FIELDS) as $f) {
            $v = (int) ($this->map[$f] ?? -1);
            $m[$f] = $v >= 0 && $v < count($this->headers) ? $v : -1;
        }
        return $m;
    }

    public function sample(): array
    {
        return $this->data()['rows'][0] ?? [];
    }

    public function firstLine(): int
    {
        return (int) ($this->data()['lines'][0] ?? 2);
    }

    public function checked(): array
    {
        if ($this->memo === null) {
            $d = $this->data();
            $this->memo = CustomerImport::check($d['rows'], $d['lines'], $this->cleanMap());
        }
        return $this->memo;
    }

    public function counts(): array
    {
        $n = ['all' => 0, 'new' => 0, 'dup' => 0, 'err' => 0, 'fix' => 0, 'fill' => 0];
        foreach ($this->checked() as $o) {
            $n['all']++;
            if ($o['st'] === 'new') $n['new']++;
            elseif ($o['st'] === 'err') $n['err']++;
            else $n['dup']++;
            if ($o['st'] !== 'err' && $o['fix']) $n['fix']++;
            if ($o['st'] === 'dupc' && $o['fill']) $n['fill']++;
        }
        if ($this->dup !== 'fill') $n['fill'] = 0;
        return $n;
    }

    public function visible(): array
    {
        $f = in_array($this->filter, ['all', 'new', 'dup', 'err', 'fix'], true) ? $this->filter : 'all';
        $L = array_filter($this->checked(), fn ($o) => match ($f) {
            'all' => true,
            'new' => $o['st'] === 'new',
            'dup' => $o['st'] === 'dupc' || $o['st'] === 'dupf',
            'err' => $o['st'] === 'err',
            'fix' => $o['st'] !== 'err' && (bool) $o['fix'],
        });
        return array_values($L);
    }

    public function setFilter(string $f): void { $this->filter = in_array($f, ['all', 'new', 'dup', 'err', 'fix'], true) ? $f : 'all'; }

    public function toStep(int $n): void
    {
        $this->err = '';
        if ($n === 1) { $this->step = 1; $this->result = null; return; }
        if ($this->token === '') return;
        if ($n === 2) { $this->step = 2; return; }
        if ($n === 3) {
            $m = $this->cleanMap();
            if ($m['name'] < 0 || $m['phone'] < 0) { $this->err = 'Chọn cột cho Họ tên và Số điện thoại để đi tiếp.'; return; }
            $this->filter = 'all';
            $this->step = 3;
        }
    }

    public function commit(): void
    {
        abort_unless(static::canAccess(), 403);
        if ($this->step !== 3 || $this->token === '') return;
        $n = $this->counts();
        if ($n['new'] + $n['fill'] === 0) return;
        $grp = array_key_exists($this->grp, Customer::GROUPS) ? $this->grp : 'le';
        $src = $this->src === '__file' || array_key_exists($this->src, Customer::SOURCES) ? $this->src : '__file';
        try {
            $this->memo = null; // check again against what is saved right now
            $b = CustomerImport::commit($this->checked(), $this->fileName, auth()->user(), ['dup' => $this->dup === 'fill' ? 'fill' : 'skip', 'source' => $src, 'group' => $grp]);
        } catch (UniqueConstraintViolationException) {
            $this->memo = null;
            $this->err = 'Có khách trùng số vừa được thêm trong lúc kiểm tra. Xem lại kết quả rồi bấm Nhập lần nữa.';
            return;
        }
        $this->result = ['id' => $b->id, 'added' => $b->added_count, 'filled' => $b->filled_count, 'skipped' => $b->skipped_count, 'errors' => $b->error_count, 'undone' => false];
        $this->step = 4;
    }

    public function undo(int $id): void
    {
        $b = CustomerImportBatch::find($id);
        if (! $b || ! $b->canUndo(auth()->user())) {
            Notification::make()->title('Không hoàn tác được lô này')->body('Chỉ Quản trị hoặc người đã nhập lô này mới hoàn tác được, và mỗi lô chỉ hoàn tác một lần.')->danger()->send();
            return;
        }
        try {
            $r = CustomerImport::undo($b, auth()->user());
        } catch (ImportError $e) {
            Notification::make()->title($e->getMessage())->warning()->send();
            return;
        }
        Notification::make()->title("Đã hoàn tác lô {$id}")->body('Bỏ ' . $r['removed'] . ' khách'
            . ($r['kept'] ? ', giữ lại ' . $r['kept'] . ' khách đã có đơn' : '')
            . ($r['restored'] ? ', trả lại thông tin cũ cho ' . $r['restored'] . ' khách' : '') . '.')->success()->send();
        if (($this->result['id'] ?? null) === $id) $this->result['undone'] = true;
    }

    public function batches()
    {
        return CustomerImportBatch::with('user')->latest('id')->limit(20)->get();
    }

    public function downloadTemplate()
    {
        return CustomerImport::download('mau-nhap-khach-hang.xlsx', [array_values(CustomerImport::FIELDS)]);
    }

    public function downloadErrors()
    {
        if ($this->token === '') return null;
        $d = $this->data();
        $rows = [['Dòng', ...$d['headers'], 'Lý do']];
        foreach ($this->checked() as $o) if ($o['st'] === 'err') $rows[] = [(string) $o['line'], ...array_map('strval', $d['rows'][$o['i']] ?? []), $o['why']];
        return CustomerImport::download('dong-loi-khach-hang.xlsx', $rows);
    }

    public function listUrl(): string
    {
        return CustomerResource::getUrl('index');
    }
}
