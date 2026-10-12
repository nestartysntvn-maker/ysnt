<?php
namespace App\Filament\Resources\CommissionPolicies\Pages;
use App\Filament\Resources\CommissionPolicies\CommissionPolicyResource;
use Filament\Resources\Pages\EditRecord;
class EditCommissionPolicy extends EditRecord
{
    protected static string $resource = CommissionPolicyResource::class;
    protected function getHeaderActions(): array { return []; }

}
