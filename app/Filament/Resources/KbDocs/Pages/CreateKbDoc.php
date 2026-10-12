<?php
namespace App\Filament\Resources\KbDocs\Pages;
use App\Filament\Resources\KbDocs\KbDocResource;
use Filament\Resources\Pages\CreateRecord;
class CreateKbDoc extends CreateRecord
{
    protected static string $resource = KbDocResource::class;

    protected function mutateFormDataBeforeCreate(array $d): array { $d['updated_by'] = auth()->id(); return $d; }
}
