<?php
namespace App\Filament\Resources\CommissionPolicies\Pages;
use App\Filament\Resources\CommissionPolicies\CommissionPolicyResource;
use Filament\Resources\Pages\ListRecords;
class ListCommissionPolicies extends ListRecords
{
    protected static string $resource = CommissionPolicyResource::class;
    protected function getHeaderActions(): array { return []; }

}
