<?php
namespace App\Filament\Resources\Customers\Pages;
use App\Filament\Resources\Customers\CustomerResource;
use App\Models\Customer;
use Filament\Resources\Pages\ListRecords;
class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;
    protected function getHeaderActions(): array
    {
        return [
            CustomerResource::createButton(),
            CustomerResource::importAction()->color('gray'),
            \App\Filament\CsvExport::action('khach-hang', fn () => Customer::withCount('orders')->withSum('orders', 'total')->orderBy('id'), [
                'Họ tên' => fn ($c) => $c->name, 'SĐT' => fn ($c) => $c->phone, 'Email' => fn ($c) => $c->email, 'Địa chỉ' => fn ($c) => $c->address,
                'Ngày sinh' => fn ($c) => $c->birthday?->format('d/m/Y'), 'Nhóm' => fn ($c) => Customer::GROUPS[$c->customer_group] ?? $c->customer_group,
                'Nguồn' => fn ($c) => Customer::SOURCES[$c->source] ?? $c->source, 'Ghi chú' => fn ($c) => $c->note,
                'Số đơn' => fn ($c) => $c->orders_count, 'Tổng chi tiêu' => fn ($c) => (int) $c->orders_sum_total,
                'Mã giới thiệu lần đầu' => fn ($c) => $c->first_ref_code, 'Ngày thêm' => fn ($c) => $c->created_at?->format('d/m/Y'),
            ]),
        ];
    }
}
