<?php
namespace App\Filament\Resources\RecruitmentJobs\Pages;
use App\Filament\Resources\RecruitmentJobs\RecruitmentJobResource;
use Filament\Resources\Pages\ListRecords;
class ListRecruitmentJobs extends ListRecords
{
    protected static string $resource = RecruitmentJobResource::class;
    protected function getHeaderActions(): array { return [\Filament\Actions\CreateAction::make()->label('Đăng tin tuyển dụng')]; }
}
