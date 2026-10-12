<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ZaloTemplate extends Model
{
    protected $guarded = [];
    protected $casts = ['params' => 'array', 'is_active' => 'boolean'];
    public function messages() { return $this->hasMany(ZaloMessage::class); }
}
