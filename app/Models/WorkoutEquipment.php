<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkoutEquipment extends Model
{
    use HasFactory;

    protected $table = 'workout_equipment';

    protected $fillable = [
        'workout_id',
        'equipment_name',
        'equipment_icon',
        'equipment_description',
        'is_required'
    ];

    protected $casts = [
        'is_required' => 'boolean'
    ];

    public function workout()
    {
        return $this->belongsTo(Workout::class);
    }
}
