<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExerciseCooldown extends Model
{
    use HasFactory;

    protected $table = 'exercise_cooldown';

    protected $fillable = [
        'exercise_id',
        'name',
        'description',
        'thumbnail',
        'duration',
        'reps',
        'order'
    ];

    protected $casts = [
        'reps' => 'integer',
        'order' => 'integer'
    ];

    public function exercise()
    {
        return $this->belongsTo(Exercise::class);
    }
}
