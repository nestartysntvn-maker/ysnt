<?php
namespace App\Filament\Resources\ChannelListings\Pages;
use App\Filament\Resources\ChannelListings\ChannelListingResource;
use Filament\Resources\Pages\EditRecord;
class EditChannelListing extends EditRecord
{
    protected static string $resource = ChannelListingResource::class;
    protected function getHeaderActions(): array { return []; }

}
