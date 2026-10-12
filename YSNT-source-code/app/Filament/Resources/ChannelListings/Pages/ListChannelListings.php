<?php
namespace App\Filament\Resources\ChannelListings\Pages;
use App\Filament\Resources\ChannelListings\ChannelListingResource;
use Filament\Resources\Pages\ListRecords;
class ListChannelListings extends ListRecords
{
    protected static string $resource = ChannelListingResource::class;
    protected function getHeaderActions(): array { return [\Filament\Actions\CreateAction::make(),]; }

}
