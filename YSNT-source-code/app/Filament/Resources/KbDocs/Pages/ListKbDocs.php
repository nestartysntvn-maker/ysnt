<?php
namespace App\Filament\Resources\KbDocs\Pages;
use App\Filament\Resources\KbDocs\KbDocResource;
use Filament\Resources\Pages\ListRecords;
class ListKbDocs extends ListRecords
{
    protected static string $resource = KbDocResource::class;
    protected function getHeaderActions(): array { return [\Filament\Actions\CreateAction::make()->label('Thêm tài liệu')]; }

}
