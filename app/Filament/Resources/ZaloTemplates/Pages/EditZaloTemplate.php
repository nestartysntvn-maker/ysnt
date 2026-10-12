<?php
namespace App\Filament\Resources\ZaloTemplates\Pages;
use App\Filament\Resources\ZaloTemplates\ZaloTemplateResource;
use Filament\Resources\Pages\EditRecord;
class EditZaloTemplate extends EditRecord
{
    protected static string $resource = ZaloTemplateResource::class;
    protected function getHeaderActions(): array { return []; }
}
