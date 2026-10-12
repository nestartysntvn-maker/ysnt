<?php
namespace Tests\Feature;

use App\Customers\CustomerImport;
use App\Filament\Resources\Customers\Pages\ImportCustomers;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Models\ActivityLog;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\CustomerImportBatch;
use App\Models\User;
use App\Support\ExportLock;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/** Runs inside a transaction that is rolled back: nothing stays in the database. */
class CustomerImportTest extends TestCase
{
    use DatabaseTransactions;

    private const CSV = "Họ và tên;SĐT;Email;Loại khách;Ngày sinh\n"
        . "Khách Thử Một;905000001;mot@example.com;Khách lẻ;15/03/1985\n"
        . "Khách Thử Hai;+84 905 000 002;hai@example;Đại lý;\n"
        . "Khách Thử Ba;0905 000 001;;;\n"
        . ";0905000004;;;\n"
        . "Khách Thử Năm;0258 3822 123;;;\n"
        . "Khách Cũ Thử;0911000001;cu@example.com;Doanh nghiệp;1985\n"
        . "Khách Thử Bảy;0168 234 5679;;Khách VIP;\n";

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getDefaultPanel());
    }

    private function admin(): User { return User::where('role', 'admin')->firstOrFail(); }

    private function staff(string $role): User
    {
        return User::forceCreate(['name' => 'Thử ' . $role, 'email' => $role . '-' . uniqid() . '@example.com', 'password' => 'mat-khau-thu-123', 'role' => $role, 'is_active' => true]);
    }

    public function test_phone_rules(): void
    {
        $ok = ['905111201' => '0905111201', '+84 912 333 402' => '0912333402', '0168 234 5678' => '0382345678', '1222888910' => '0772888910',
            '84976777809' => '0976777809', '0987.555.608 / 0905.666.708' => '0987555608', '(+84) 0905 123 456' => '0905123456', '9.05111201E+8' => '0905111201', '0905-123-456' => '0905123456'];
        foreach ($ok as $in => $want) $this->assertSame([$want, null], [CustomerImport::phone($in)['v'], CustomerImport::phone($in)['err']], $in);
        $this->assertSame(['0905.666.708'], CustomerImport::phone('0987.555.608 / 0905.666.708')['extra']);
        $this->assertSame('Số máy bàn, cần số di động', CustomerImport::phone('0258 3822 123')['err']);
        $this->assertSame('Số điện thoại sai, cần 10 số di động', CustomerImport::phone('12345')['err']);
        $this->assertSame('Thiếu số điện thoại', CustomerImport::phone('')['err']);
    }

    public function test_birthday_group_source(): void
    {
        $this->assertSame('1985-03-15', CustomerImport::birthday('15/03/1985'));
        $this->assertSame('1985-03-15', CustomerImport::birthday('3/15/1985'));
        $this->assertSame('1990-09-02', CustomerImport::birthday('02-09-90'));
        $this->assertSame('1985-03-15', CustomerImport::birthday('31121'));
        $this->assertNull(CustomerImport::birthday('1985'));
        $this->assertNull(CustomerImport::birthday('31/02/1985'));
        $this->assertSame('doanh_nghiep', CustomerImport::group('Khách doanh nghiệp'));
        $this->assertSame('dai_ly', CustomerImport::group('ĐẠI LÝ'));
        $this->assertSame('le', CustomerImport::group('Khách lẻ'));
        $this->assertNull(CustomerImport::group('Khách VIP'));
        $this->assertSame('tiktok', CustomerImport::source('TikTok Shop'));
        $this->assertSame('hdv', CustomerImport::source('Hướng dẫn viên'));
    }

    public function test_check_commit_undo(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        Customer::create(['name' => 'Khách Cũ Thử', 'phone' => '0911000001']);
        $p = CustomerImport::prepare(CustomerImport::parseDelimited(self::CSV));
        $this->assertSame(['name' => 0, 'phone' => 1, 'email' => 2, 'address' => -1, 'birthday' => 4, 'group' => 3, 'source' => -1, 'note' => -1], $p['map']);
        $L = CustomerImport::check($p['rows'], $p['lines'], $p['map']);
        $st = array_map(fn ($o) => $o['line'] . ':' . $o['st'], $L);
        $this->assertSame(['2:new', '3:new', '4:dupf', '5:err', '6:err', '7:dupc', '8:new'], $st);
        $this->assertSame('0382345679', $L[6]['phone']);
        $this->assertStringContainsString('Nhóm trong file: Khách VIP', $L[6]['note']);
        $this->assertSame('', $L[1]['email']);
        $this->assertSame(['email' => 'cu@example.com', 'note' => 'Ngày sinh: 1985'], $L[5]['fill']);

        $logs = ActivityLog::count();
        $b = CustomerImport::commit($L, 'khach-thu.csv', $admin, ['dup' => 'fill', 'source' => '__file', 'group' => 'le']);
        $this->assertSame([3, 1, 1, 2], [$b->added_count, $b->filled_count, $b->skipped_count, $b->error_count]);
        $this->assertSame($logs + 1, ActivityLog::count(), 'one summary line, not one per customer');
        $c = Customer::where('phone', '0905000001')->firstOrFail();
        $this->assertSame(['le', 'danh_sach_cu', '1985-03-15', $b->id], [$c->customer_group, $c->source, $c->birthday->format('Y-m-d'), $c->import_batch_id]);
        $this->assertSame('dai_ly', Customer::where('phone', '0905000002')->value('customer_group'));
        $this->assertSame('cu@example.com', Customer::where('phone', '0911000001')->value('email'));

        $r = CustomerImport::undo($b, $admin);
        $this->assertSame(['removed' => 3, 'kept' => 0, 'restored' => 1], $r);
        $this->assertSame(0, Customer::where('import_batch_id', $b->id)->count());
        $this->assertNull(Customer::where('phone', '0911000001')->value('email'));
        $this->assertNotNull($b->fresh()->undone_at);
        $this->assertFalse($b->fresh()->canUndo($admin));
    }

    public function test_xlsx_with_title_row_and_number_cells(): void
    {
        $book = new Spreadsheet();
        $book->getActiveSheet()->setTitle('Hướng dẫn')->setCellValue('A1', 'Danh sách ở trang bên cạnh');
        $ws = $book->createSheet()->setTitle('Khách 2024');
        $ws->fromArray([['DANH SÁCH KHÁCH HÀNG CŨ'], [null], ['STT', 'Họ và tên', 'SĐT', 'Ngày sinh'], [1, 'Khách Excel Một', 905000011, null], [2, 'Khách Excel Hai', 84905000012, null]]);
        $ws->setCellValue('D4', 31121);
        $ws->getStyle('D4')->getNumberFormat()->setFormatCode('dd/mm/yyyy');
        $base = tempnam(sys_get_temp_dir(), 'kh');
        $path = $base . '.xlsx';
        (new Xlsx($book))->save($path);
        [$rows, $sheet] = CustomerImport::readFile($path, 'khach-cu.xlsx');
        @unlink($path);
        @unlink($base);
        $this->assertSame('Khách 2024', $sheet);
        $p = CustomerImport::prepare($rows);
        $this->assertSame(3, $p['headRow']);
        $this->assertSame([4, 5], $p['lines']);
        $L = CustomerImport::check($p['rows'], $p['lines'], $p['map']);
        $this->assertSame(['0905000011', '0905000012'], array_column($L, 'phone'));
        $this->assertSame('1985-03-15', $L[0]['birthday']);
    }

    public function test_import_page_flow_and_access(): void
    {
        $this->actingAs($this->staff('warehouse'));
        $this->assertFalse(ImportCustomers::canAccess());
        $this->actingAs($this->staff('sales'));
        $this->assertTrue(ImportCustomers::canAccess());

        $file = UploadedFile::fake()->createWithContent('khach-thu.csv', self::CSV);
        $lw = Livewire::test(ImportCustomers::class)->set('upload', $file)->assertSet('step', 2)->assertSet('total', 7)->assertSet('err', '');
        $lw->call('toStep', 3)->assertSet('step', 3)->assertSee('Nhập 4 khách mới');
        $lw->call('setFilter', 'err')->assertSee('Số máy bàn, cần số di động')->assertDontSee('Khách Thử Một');
        $lw->call('commit')->assertSet('step', 4)->assertSee('Đã nhập xong lô');
        $id = $lw->get('result')['id'];
        $this->assertSame(4, Customer::where('import_batch_id', $id)->count());
        $lw->call('undo', $id)->assertSee('Đã hoàn tác lô');
        $this->assertSame(0, Customer::where('import_batch_id', $id)->count());
    }

    public function test_paste_without_header(): void
    {
        $this->actingAs($this->admin());
        Livewire::test(ImportCustomers::class)->set('pasted', "Khách Dán Một\t0905000021\nKhách Dán Hai\t0905000022")->call('usePaste')
            ->assertSet('step', 2)->assertSet('headRow', 0)->assertSet('map.name', 0)->assertSet('map.phone', 1)->assertSee('không có dòng tiêu đề');
    }

    public function test_manual_add(): void
    {
        $this->actingAs($this->staff('sales'));
        $this->assertTrue(\App\Filament\Resources\Customers\CustomerResource::canCreate());
        Livewire::test(\App\Filament\Resources\Customers\Pages\CreateCustomer::class)
            ->fillForm(['name' => 'Khách Tay Thử', 'phone' => '+84 905 000 031', 'customer_group' => 'doanh_nghiep', 'source' => 'showroom', 'birthday' => '1990-09-02'])
            ->call('create')->assertHasNoFormErrors();
        $c = Customer::where('phone', '0905000031')->first();
        $this->assertNotNull($c, 'phone saved as 0905000031');
        $this->assertSame(['Khách Tay Thử', 'doanh_nghiep', 'showroom', '1990-09-02'], [$c->name, $c->customer_group, $c->source, $c->birthday->format('Y-m-d')]);
        Livewire::test(\App\Filament\Resources\Customers\Pages\CreateCustomer::class)
            ->fillForm(['name' => 'Khách Trùng Thử', 'phone' => '0905 000 031'])->call('create')->assertHasFormErrors(['phone']);
        Livewire::test(\App\Filament\Resources\Customers\Pages\CreateCustomer::class)
            ->fillForm(['name' => 'Khách Máy Bàn', 'phone' => '0258 3822 123'])->call('create')->assertHasFormErrors(['phone']);
        Livewire::test(\App\Filament\Resources\Customers\Pages\EditCustomer::class, ['record' => $c->getRouteKey()])
            ->fillForm(['note' => 'Khách quen'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('Khách quen', $c->fresh()->note);
        $this->actingAs($this->staff('warehouse'));
        $this->assertFalse(\App\Filament\Resources\Customers\CustomerResource::canCreate());
    }

    public function test_export_lock(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $sales = $this->staff('sales');
        AppSetting::put('export_lock', '1');
        AppSetting::put('export_allow_' . $sales->id, '0');
        $this->assertTrue(ExportLock::allows($admin));
        $this->assertFalse(ExportLock::allows($sales));
        AppSetting::put('export_allow_' . $sales->id, '1');
        $this->assertTrue(ExportLock::allows($sales));
        AppSetting::put('export_allow_' . $sales->id, '0');

        $this->actingAs($sales);
        $before = ActivityLog::where('action', 'export')->count();
        Livewire::test(ListCustomers::class)->callAction('csv')->assertNotified('Xuất file đang khóa');
        $this->assertSame($before, ActivityLog::where('action', 'export')->count());

        $this->actingAs($admin);
        Livewire::test(ListCustomers::class)->callAction('csv');
        $this->assertSame($before + 1, ActivityLog::where('action', 'export')->count());
        $log = ActivityLog::where('action', 'export')->latest('id')->first();
        $this->assertSame([$admin->id, 'Customer'], [$log->user_id, $log->subject_type]);

        AppSetting::put('export_lock', '0');
        $this->assertTrue(ExportLock::allows($sales));
    }
}
