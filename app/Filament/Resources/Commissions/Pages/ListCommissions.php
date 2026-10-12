<?php
namespace App\Filament\Resources\Commissions\Pages;
use App\Filament\Resources\Commissions\CommissionResource;
use Filament\Resources\Pages\ListRecords;
class ListCommissions extends ListRecords
{
    protected static string $resource = CommissionResource::class;
    protected function getHeaderActions(): array { return [\App\Filament\CsvExport::action("hoa-hong", fn () => \App\Models\Commission::with(["order", "ambassador"])->orderBy("id"), ["Đơn" => fn ($c) => $c->order?->code, "Đại sứ" => fn ($c) => $c->ambassador?->name, "Mã" => fn ($c) => $c->ambassador?->ref_code, "Doanh số tính" => fn ($c) => $c->base_amount, "%" => fn ($c) => $c->rate_percent, "Hoa hồng" => fn ($c) => $c->amount, "Trạng thái" => fn ($c) => \App\Filament\Resources\Commissions\CommissionResource::STATUS[$c->status] ?? $c->status, "Ngày" => fn ($c) => $c->created_at?->format("d/m/Y")]),]; }

}
