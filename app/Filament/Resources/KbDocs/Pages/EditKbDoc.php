<?php
namespace App\Filament\Resources\KbDocs\Pages;
use App\Filament\Resources\KbDocs\KbDocResource;
use Filament\Resources\Pages\EditRecord;
class EditKbDoc extends EditRecord
{
    protected static string $resource = KbDocResource::class;

    protected function mutateFormDataBeforeSave(array $d): array { $d['updated_by'] = auth()->id(); return $d; }
}
