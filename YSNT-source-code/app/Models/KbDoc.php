<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class KbDoc extends Model
{
    protected $guarded = [];
    protected $casts = ['is_published' => 'boolean'];
    public function department() { return $this->belongsTo(Department::class); }
    public function owner() { return $this->belongsTo(Employee::class, 'owner_employee_id'); }
    /** everything the AI and the search read: typed body + text pulled out of the uploaded file */
    public function text(): string { return trim(strip_tags((string) $this->body) . "\n\n" . (string) $this->file_text); }
    /** docs one employee may read: company-wide + their own department */
    protected static function booted(): void
    {
        // a new or replaced file is read once here, so search and the AI work on its text
        static::saving(function (KbDoc $d) {
            if (! $d->isDirty('file_path')) return;
            $d->file_text = null;
            if (! $d->file_path) { $d->file_name = null; return; }
            try { $d->file_text = \App\Hr\Knowledge::extract(\Illuminate\Support\Facades\Storage::disk('local')->path($d->file_path), $d->file_name ?: $d->file_path); }
            catch (\Throwable $e) { IntegrationLog::write('kb', 'extract', 'error', $e->getMessage(), null, (string) $d->file_name); }
        });
    }

    public function scopeVisibleTo($q, ?Employee $e) { return $q->where('is_published', true)->where(fn ($w) => $w->whereNull('department_id')->orWhere('department_id', $e?->department_id)); }
}
