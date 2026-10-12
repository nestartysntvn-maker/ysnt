<?php
namespace App\Filament\Resources\JobApplications\Pages;
use App\Filament\Resources\JobApplications\JobApplicationResource;
use Filament\Resources\Pages\CreateRecord;
class CreateJobApplication extends CreateRecord
{
    protected static string $resource = JobApplicationResource::class;
    // added by HR (phone call, walk-in): no emails, as go365
    protected function mutateFormDataBeforeCreate(array $d): array { $d['source'] = 'manual'; $d['status'] = 'new'; return $d; }
}
