<?php
namespace App\Filament\Resources\SalesTargets\Pages;
use App\Filament\Resources\SalesTargets\SalesTargetResource;
use Filament\Resources\Pages\ListRecords;
class ListSalesTargets extends ListRecords
{
    protected static string $resource = SalesTargetResource::class;
    protected function getHeaderActions(): array { return []; }
}
