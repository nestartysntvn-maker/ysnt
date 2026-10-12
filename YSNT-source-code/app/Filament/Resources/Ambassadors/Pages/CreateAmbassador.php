<?php
namespace App\Filament\Resources\Ambassadors\Pages;
use App\Filament\Resources\Ambassadors\AmbassadorResource;
use Filament\Resources\Pages\CreateRecord;
class CreateAmbassador extends CreateRecord
{
    protected static string $resource = AmbassadorResource::class;
    protected function getHeaderActions(): array { return []; }
    protected function mutateFormDataBeforeCreate(array $d): array { if (empty($d["ref_code"])) $d["ref_code"] = \App\Filament\Resources\Ambassadors\AmbassadorResource::makeCode($d["name"], $d["type"]); return $d; }
}
