<?php
namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use Filament\Resources\Pages\CreateRecord;

/** Khách hàng > Thêm khách: one customer typed in by hand (logged like any other change). */
class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;
    protected static ?string $title = 'Thêm khách hàng';
    protected static ?string $breadcrumb = 'Thêm khách';

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Đã thêm khách ' . ($this->record?->name ?? '');
    }
}
