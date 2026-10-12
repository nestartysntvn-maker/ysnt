<?php
namespace App\Filament\Resources\KbQuestions\Pages;
use App\Filament\Resources\KbQuestions\KbQuestionResource;
use Filament\Resources\Pages\ListRecords;
class ListKbQuestions extends ListRecords
{
    protected static string $resource = KbQuestionResource::class;
    protected function getHeaderActions(): array { return []; }
}
