<?php
namespace App\Filament\Resources\TourVisits\Pages;
use App\Filament\Resources\TourVisits\TourVisitResource;
use Filament\Resources\Pages\ListRecords;
class ListTourVisits extends ListRecords
{
    protected static string $resource = TourVisitResource::class;
    protected function getHeaderActions(): array { return [\Filament\Actions\CreateAction::make()->label("Ghi xe mới"),]; }
}
