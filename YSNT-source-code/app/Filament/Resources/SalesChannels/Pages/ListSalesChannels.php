<?php
namespace App\Filament\Resources\SalesChannels\Pages;
use App\Filament\Resources\SalesChannels\SalesChannelResource;
use Filament\Resources\Pages\ListRecords;
class ListSalesChannels extends ListRecords
{
    protected static string $resource = SalesChannelResource::class;
    protected function getHeaderActions(): array { return [\Filament\Actions\CreateAction::make(),]; }

}
