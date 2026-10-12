<?php
namespace App\Filament\Resources\Ambassadors\Pages;
use App\Filament\Resources\Ambassadors\AmbassadorResource;
use Filament\Resources\Pages\ListRecords;
class ListAmbassadors extends ListRecords
{
    protected static string $resource = AmbassadorResource::class;
    protected function getHeaderActions(): array { return [\Filament\Actions\CreateAction::make(),]; }

}
