<?php
namespace App\Filament\Resources\TourVisits\Pages;
use App\Filament\Resources\TourVisits\TourVisitResource;
use Filament\Resources\Pages\EditRecord;
class EditTourVisit extends EditRecord
{
    protected static string $resource = TourVisitResource::class;
    protected function getHeaderActions(): array { return []; }
}
