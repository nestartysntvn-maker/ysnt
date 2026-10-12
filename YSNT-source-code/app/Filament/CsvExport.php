<?php
namespace App\Filament;

use App\Models\ActivityLog;
use App\Support\ExportLock;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

/**
 * "Xuất Excel" button: streams a UTF-8 CSV (with BOM so Excel shows Vietnamese correctly).
 * Respects Hệ thống > Khóa xuất file; every export is written to the activity log (who, which list, how many rows).
 */
class CsvExport
{
    /** @param array<string, callable> $cols label => fn($row) */
    public static function action(string $file, callable $query, array $cols): Action
    {
        $can = fn (): bool => ExportLock::allows(auth()->user());
        return Action::make('csv')->label('Xuất Excel')->color('gray')
            ->icon(fn () => $can() ? Heroicon::OutlinedArrowDownTray : Heroicon::OutlinedLockClosed)
            ->tooltip(fn () => $can() ? null : 'Xuất file đang khóa. Liên hệ quản trị để được mở quyền.')
            ->action(function () use ($file, $query, $cols, $can) {
                if (! $can()) {
                    Notification::make()->title('Xuất file đang khóa')->body('Liên hệ quản trị để được mở quyền xuất file.')->warning()->send();
                    return null;
                }
                $name = $file . '-' . now()->format('Ymd-His') . '.csv';
                /** @var Builder $first */
                $first = $query();
                ActivityLog::create(['user_id' => auth()->id(), 'action' => 'export', 'subject_type' => class_basename($first->getModel()),
                    'changes' => ['file' => $name, 'rows' => $first->count()], 'ip' => request()?->ip()]);
                return response()->streamDownload(function () use ($query, $cols) {
                    $out = fopen('php://output', 'w');
                    fwrite($out, "\xEF\xBB\xBF");
                    fputcsv($out, array_keys($cols));
                    /** @var Builder $q */
                    $q = $query();
                    $q->chunk(500, function ($rows) use ($out, $cols) {
                        foreach ($rows as $r) fputcsv($out, array_map(fn ($f) => $f($r), array_values($cols)));
                    });
                    fclose($out);
                }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
            });
    }
}
