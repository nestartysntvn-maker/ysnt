<?php
namespace App\Filament\Resources\Orders\Pages;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Resources\Pages\ListRecords;
class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;
    protected function getHeaderActions(): array { return [\App\Filament\CsvExport::action("don-hang", fn () => \App\Models\Order::with("items")->orderBy("id"), ["Mã đơn" => fn ($o) => $o->code, "Ngày" => fn ($o) => $o->created_at?->format("d/m/Y H:i"), "Kênh" => fn ($o) => \App\Filament\Resources\Orders\OrderResource::CHANNEL[$o->channel] ?? $o->channel, "Mã đơn trên sàn" => fn ($o) => $o->channel_order_id, "Khách" => fn ($o) => $o->ship_name, "SĐT" => fn ($o) => $o->ship_phone, "Địa chỉ" => fn ($o) => $o->ship_address, "Sản phẩm" => fn ($o) => $o->items->map(fn ($i) => $i->product_name . " - " . $i->variant_name . " x" . $i->qty)->implode("; "), "Tạm tính" => fn ($o) => $o->subtotal, "Giảm" => fn ($o) => $o->discount, "Phí ship" => fn ($o) => $o->shipping_fee, "Tổng" => fn ($o) => $o->total, "Trạng thái" => fn ($o) => \App\Filament\Resources\Orders\OrderResource::STATUS[$o->status] ?? $o->status, "Thanh toán" => fn ($o) => \App\Filament\Resources\Orders\OrderResource::PAY_STATUS[$o->payment_status] ?? $o->payment_status, "Mã giới thiệu" => fn ($o) => $o->ref_code]),]; }

}
