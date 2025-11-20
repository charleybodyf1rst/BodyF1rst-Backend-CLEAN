<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkoutLibrary extends Model
{
    use HasFactory;

    protected $table = 'workout_library';

    protected $fillable = [
        'created_by_admin_id',
        'name',
        'description',
        'category',
        'difficulty_level',
        'goal',
        'duration_weeks',
        'sessions_per_week',
        'exercises',
        'tags',
        'thumbnail_url',
        'is_featured',
        'clone_count',
        'notes'
    ];

    protected $casts = [
        'exercises' => 'array',
        'tags' => 'array',
        'is_featured' => 'boolean',
        'clone_count' => 'integer',
        'duration_weeks' => 'integer',
        'sessions_per_week' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the admin who created this workout
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_admin_id');
    }

    /**
     * Scope to get featured workouts
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope to filter by category
     */
    public function scopeCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope to filter by difficulty
     */
    public function scopeDifficulty($query, $difficulty)
    {
        return $query->where('difficulty_level', $difficulty);
    }

    /**
     * Increment clone count
     */
    public function incrementCloneCount()
    {
        $this->increment('clone_count');
    }
}
