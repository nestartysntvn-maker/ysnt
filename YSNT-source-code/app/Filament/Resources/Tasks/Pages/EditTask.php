<?php
namespace App\Filament\Resources\Tasks\Pages;
use App\Filament\Resources\Tasks\TaskResource;
use App\Hr\Tasks;
use Filament\Resources\Pages\EditRecord;
class EditTask extends EditRecord
{
    protected static string $resource = TaskResource::class;
    private ?string $oldStatus = null;
    protected function mutateFormDataBeforeSave(array $d): array
    {
        $this->oldStatus = $this->record->status;
        // a new deadline gets its own reminder and overdue notice
        if (array_key_exists('due_at', $d) && (string) $d['due_at'] !== (string) $this->record->due_at) { $d['reminded_at'] = null; $d['overdue_notified_at'] = null; }
        $s = $d['status'] ?? $this->record->status; unset($d['status']);
        $this->pending = $s;
        return $d;
    }
    public ?string $pending = null;
    protected function afterSave(): void
    {
        if ($this->pending && $this->pending !== $this->oldStatus) Tasks::setStatus($this->record, $this->pending, null, null, auth()->user());
        $c = trim((string) ($this->data['comment'] ?? ''));
        if ($c !== '') { Tasks::log($this->record, 'comment', $c, null, auth()->user()); Tasks::notifyAssignee($this->record, 'Trao đổi về việc', $c); $this->data['comment'] = ''; }
    }
}
