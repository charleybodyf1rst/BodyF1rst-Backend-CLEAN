<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FitnessVideosLibrary extends Model
{
    use HasFactory;

    protected $table = 'fitness_videos_library';

    protected $fillable = [
        'created_by_admin_id',
        'title',
        'description',
        'video_url',
        'thumbnail_url',
        'duration_seconds',
        'category',
        'difficulty_level',
        'tags',
        'is_featured',
        'view_count',
        'clone_count',
        'notes'
    ];

    protected $casts = [
        'tags' => 'array',
        'is_featured' => 'boolean',
        'view_count' => 'integer',
        'clone_count' => 'integer',
        'duration_seconds' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the admin who created this video
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_admin_id');
    }

    /**
     * Scope to get featured videos
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
     * Increment view count
     */
    public function incrementViewCount()
    {
        $this->increment('view_count');
    }

    /**
     * Increment clone count
     */
    public function incrementCloneCount()
    {
        $this->increment('clone_count');
    }
}
