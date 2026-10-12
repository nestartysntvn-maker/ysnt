<?php
namespace App\Filament\Resources\Ambassadors\Pages;
use App\Filament\Resources\Ambassadors\AmbassadorResource;
use Filament\Resources\Pages\EditRecord;
class EditAmbassador extends EditRecord
{
    protected static string $resource = AmbassadorResource::class;
    protected function getHeaderActions(): array { return []; }

}
