<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NutritionLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'meals',
        'total_calories',
        'macros',
        'water_intake',
        'exercise_calories_burned',
        'synced_at'
    ];

    protected $casts = [
        'date' => 'date',
        'meals' => 'array',
        'total_calories' => 'float',
        'macros' => 'array',
        'water_intake' => 'float',
        'exercise_calories_burned' => 'float',
        'synced_at' => 'datetime'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
