<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AnnouncementRecipient extends Model
{
    protected $guarded = [];
    protected $casts = ['read_at' => 'datetime'];
    public function announcement() { return $this->belongsTo(Announcement::class); }
    public function employee() { return $this->belongsTo(Employee::class); }
}
