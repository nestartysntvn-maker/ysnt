<?php
namespace App\Filament\Resources\SeoMetas\Pages;
use App\Ai\Assist;
use App\Filament\Resources\SeoMetas\SeoMetaResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
class EditSeoMeta extends EditRecord
{
    protected static string $resource = SeoMetaResource::class;
    protected function getHeaderActions(): array
    {
        // fills the form only; nothing is saved until "Lưu"
        return [Action::make('ai')->label('AI gợi ý')->icon(Heroicon::OutlinedSparkles)->action(function () {
            $r = Assist::seo($this->record->page_type, $this->record->page_key);
            if ($r['status'] !== 'ok') { Notification::make()->title('AI chưa gợi ý được')->body($r['error'])->warning()->send(); return; }
            $this->form->fill(array_merge($this->form->getRawState(), ['meta_title' => $r['data']['title'], 'meta_description' => $r['data']['description'], 'focus_keyword' => $this->data['focus_keyword'] ?: $r['data']['keyword']]));
            Notification::make()->title('Đã điền gợi ý, xem lại rồi bấm Lưu')->success()->send();
        })];
    }
    protected function mutateFormDataBeforeSave(array $d): array { $d['updated_by'] = auth()->id(); return $d; }
}
