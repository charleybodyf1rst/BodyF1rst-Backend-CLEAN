<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkoutCalendar extends Model
{
    use HasFactory;

    protected $table = 'workout_calendar';

    protected $fillable = [
        'user_id',
        'workout_id',
        'scheduled_date',
        'scheduled_time',
        'status',
        'notes',
        'completed_at'
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'scheduled_time' => 'datetime:H:i',
        'completed_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function workout()
    {
        return $this->belongsTo(Workout::class);
    }
}
