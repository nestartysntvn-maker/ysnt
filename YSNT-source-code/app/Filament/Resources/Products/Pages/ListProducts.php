<?php
namespace App\Filament\Resources\Products\Pages;
use App\Filament\Resources\Products\ProductResource;
use Filament\Resources\Pages\ListRecords;
class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;
    protected function getHeaderActions(): array { return [\App\Filament\CsvExport::action("san-pham", fn () => \App\Models\ProductVariant::with("product.category")->orderBy("product_id")->orderBy("sort_order"), ["Sản phẩm" => fn ($v) => $v->product?->name, "Nhóm" => fn ($v) => $v->product?->category?->name, "Quy cách" => fn ($v) => $v->name, "SKU" => fn ($v) => $v->sku, "Giá niêm yết" => fn ($v) => $v->list_price, "Tồn" => fn ($v) => $v->stock_qty, "Còn bán" => fn ($v) => $v->is_available ? "Có" : "Không"]), \Filament\Actions\CreateAction::make(),]; }

}
