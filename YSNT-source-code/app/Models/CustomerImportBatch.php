<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One uploaded customer list. `filled` keeps the blank fields it filled on existing customers so undo can put them back. */
class CustomerImportBatch extends Model
{
    protected $guarded = [];
    protected $casts = ['filled' => 'array', 'undone_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }
    public function undoer() { return $this->belongsTo(User::class, 'undone_by'); }
    public function customers() { return $this->hasMany(Customer::class, 'import_batch_id'); }

    /** admin, or whoever imported it; only once */
    public function canUndo(?User $u): bool
    {
        return $u !== null && $this->undone_at === null && ($u->role === 'admin' || $u->id === $this->user_id);
    }
}
