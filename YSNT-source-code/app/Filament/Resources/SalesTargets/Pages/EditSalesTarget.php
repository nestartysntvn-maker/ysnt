<?php
namespace App\Filament\Resources\SalesTargets\Pages;
use App\Filament\Resources\SalesTargets\SalesTargetResource;
use Filament\Resources\Pages\EditRecord;
class EditSalesTarget extends EditRecord
{
    protected static string $resource = SalesTargetResource::class;
    protected function getHeaderActions(): array { return []; }
}
