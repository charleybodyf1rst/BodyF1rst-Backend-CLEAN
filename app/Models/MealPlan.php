<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'passio_plan_id',
        'name',
        'description',
        'date',
        'total_calories',
        'macros',
        'meals',
        'preferences'
    ];

    protected $casts = [
        'date' => 'date',
        'total_calories' => 'integer',
        'macros' => 'array',
        'meals' => 'array',
        'preferences' => 'array'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
