<?php
namespace App\Filament\Resources\PayoutRequests\Pages;
use App\Filament\Resources\PayoutRequests\PayoutRequestResource;
use Filament\Resources\Pages\ListRecords;
class ListPayoutRequests extends ListRecords
{
    protected static string $resource = PayoutRequestResource::class;
    protected function getHeaderActions(): array { return [\Filament\Actions\CreateAction::make(),]; }

}
