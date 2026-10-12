<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SalesTarget extends Model { protected $guarded = []; protected $casts = ['period' => 'date']; }
