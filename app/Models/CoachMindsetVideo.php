<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CoachMindsetVideo extends Model
{
    use HasFactory;

    protected $table = 'coach_mindset_videos';

    protected $fillable = [
        'coach_id',
        'title',
        'description',
        'video_url',
        'thumbnail_url',
        'duration_seconds',
        'category',
        'tags',
        'view_count',
        'notes',
        'cloned_from_library_id'
    ];

    protected $casts = [
        'tags' => 'array',
        'view_count' => 'integer',
        'duration_seconds' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the coach who owns this video
     */
    public function coach()
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    /**
     * Get the library video this was cloned from
     */
    public function sourceLibraryVideo()
    {
        return $this->belongsTo(MindsetVideosLibrary::class, 'cloned_from_library_id');
    }

    /**
     * Scope to filter by coach
     */
    public function scopeForCoach($query, $coachId)
    {
        return $query->where('coach_id', $coachId);
    }

    /**
     * Scope to filter by category
     */
    public function scopeCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Increment view count
     */
    public function incrementViewCount()
    {
        $this->increment('view_count');
    }
}
