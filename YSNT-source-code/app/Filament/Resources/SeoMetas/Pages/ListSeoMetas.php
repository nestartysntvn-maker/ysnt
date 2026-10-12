<?php
namespace App\Filament\Resources\SeoMetas\Pages;
use App\Filament\Resources\SeoMetas\SeoMetaResource;
use Filament\Resources\Pages\ListRecords;
class ListSeoMetas extends ListRecords
{
    protected static string $resource = SeoMetaResource::class;
    protected function getHeaderActions(): array { return []; }
}
