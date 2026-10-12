<?php
namespace App\Filament\Resources\PayoutRequests\Pages;
use App\Filament\Resources\PayoutRequests\PayoutRequestResource;
use Filament\Resources\Pages\CreateRecord;
class CreatePayoutRequest extends CreateRecord
{
    protected static string $resource = PayoutRequestResource::class;
    protected function getHeaderActions(): array { return []; }
    protected function mutateFormDataBeforeCreate(array $d): array { $d["status"] = "pending"; return $d; }
}
