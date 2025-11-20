<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyTask extends Model
{
    use HasFactory;

    protected $table = 'daily_tasks';

    protected $fillable = [
        'user_id',
        'date',
        'goal',
        'time',
        'scheduled',
        'completed'
    ];

    protected $casts = [
        'date' => 'date',
        'scheduled' => 'boolean',
        'completed' => 'boolean'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
