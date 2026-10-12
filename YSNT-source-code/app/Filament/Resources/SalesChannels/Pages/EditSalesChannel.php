<?php
namespace App\Filament\Resources\SalesChannels\Pages;
use App\Filament\Resources\SalesChannels\SalesChannelResource;
use Filament\Resources\Pages\EditRecord;
class EditSalesChannel extends EditRecord
{
    protected static string $resource = SalesChannelResource::class;
    protected function getHeaderActions(): array { return []; }
    protected function mutateFormDataBeforeFill(array $d): array { foreach (\App\Filament\Resources\SalesChannels\SalesChannelResource::SECRETS as $k) unset($d["credentials"][$k]); return $d; }
    protected function mutateFormDataBeforeSave(array $d): array { $old = $this->record->credentials ?? []; foreach (($d["credentials"] ?? []) as $k => $v) if ($v === null || $v === "") unset($d["credentials"][$k]); $d["credentials"] = array_merge($old, $d["credentials"] ?? []); return $d; }
}
