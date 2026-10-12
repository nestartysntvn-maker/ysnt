<?php
namespace App\Filament\Resources\ZaloMessages\Pages;
use App\Filament\Resources\ZaloMessages\ZaloMessageResource;
use Filament\Resources\Pages\ListRecords;
class ListZaloMessages extends ListRecords
{
    protected static string $resource = ZaloMessageResource::class;
    protected function getHeaderActions(): array { return []; }
}
